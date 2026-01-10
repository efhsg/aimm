<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\adapters\SourceAdapterInterface;
use app\clients\FetchRequest;
use app\clients\FmpResponseCache;
use app\clients\UrlSanitizer;
use app\clients\WebFetchClientInterface;
use app\dto\AdaptRequest;
use app\dto\CollectBatchRequest;
use app\dto\CollectBatchResult;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\HistoricalExtraction;
use app\dto\SourceAttempt;
use app\dto\SourceCandidate;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\exceptions\RateLimitException;
use DateTimeImmutable;
use Throwable;
use yii\log\Logger;

/**
 * Batch datapoint collector with early-exit optimization.
 *
 * Collects multiple datapoints in a single operation, grouping sources by URL
 * to minimize fetches and exiting early when all required keys are satisfied.
 */
final class CollectBatchHandler implements CollectBatchInterface
{
    private const OUTCOME_SUCCESS = 'success';
    private const OUTCOME_HTTP_ERROR = 'http_error';
    private const OUTCOME_PARSE_FAILED = 'parse_failed';
    private const OUTCOME_NOT_IN_PAGE = 'not_in_page';
    private const OUTCOME_RATE_LIMITED = 'rate_limited';
    private const OUTCOME_BLOCKED = 'blocked';
    private const OUTCOME_TIMEOUT = 'timeout';
    private const OUTCOME_NETWORK_ERROR = 'network_error';
    private const OUTCOME_PARTIAL = 'partial';

    private ?FmpResponseCache $responseCache = null;

    public function __construct(
        private readonly WebFetchClientInterface $webFetchClient,
        private readonly SourceAdapterInterface $sourceAdapter,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectBatchRequest $request): CollectBatchResult
    {
        $this->logger->log(
            [
                'message' => 'Starting batch collection',
                'datapoint_count' => count($request->datapointKeys),
                'required_count' => count($request->requiredKeys),
                'source_count' => count($request->sourceCandidates),
                'ticker' => $request->ticker,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $this->responseCache = new FmpResponseCache();

        $found = [];
        $historicalFound = [];
        $missing = $request->datapointKeys;
        $requiredSet = array_flip($request->requiredKeys);
        $allAttempts = [];

        // Group and iterate through sources by URL
        foreach ($this->groupByUrl($request->sourceCandidates) as $sourceGroup) {
            $candidate = $sourceGroup['candidate'];

            // Check rate limiting
            if ($this->webFetchClient->isRateLimited($candidate->domain)) {
                $this->logger->log(
                    [
                        'message' => 'Source rate limited, skipping',
                        'url' => UrlSanitizer::sanitize($candidate->url),
                        'domain' => $candidate->domain,
                    ],
                    Logger::LEVEL_INFO,
                    'collection'
                );
                $allAttempts[] = new SourceAttempt(
                    url: UrlSanitizer::sanitize($candidate->url),
                    providerId: $candidate->adapterId,
                    attemptedAt: new DateTimeImmutable(),
                    outcome: self::OUTCOME_RATE_LIMITED,
                    reason: 'Domain is currently rate limited',
                );
                continue;
            }

            // Only request keys we still need (not already found)
            $keysToExtract = array_values(array_intersect($missing, $request->datapointKeys));
            if ($keysToExtract === []) {
                continue;
            }

            $attemptResult = $this->trySource($candidate, $keysToExtract, $request);
            $allAttempts[] = $attemptResult['attempt'];

            // Merge found extractions
            foreach ($attemptResult['extractions'] as $key => $extraction) {
                $found[$key] = $extraction;
                $missing = array_values(array_diff($missing, [$key]));
            }
            foreach ($attemptResult['historicalExtractions'] as $key => $hist) {
                $historicalFound[$key] = $hist;
                $missing = array_values(array_diff($missing, [$key]));
            }

            // Early-exit: stop if all required keys found
            $foundKeys = array_merge(array_keys($found), array_keys($historicalFound));
            $missingRequired = array_diff_key($requiredSet, array_flip($foundKeys));
            if ($missingRequired === []) {
                $this->logger->log(
                    [
                        'message' => 'Early exit - all required keys satisfied',
                        'required_count' => count($request->requiredKeys),
                        'optional_remaining' => count($missing),
                    ],
                    Logger::LEVEL_INFO,
                    'collection'
                );
                break;
            }
        }

        $this->responseCache = null;

        $foundKeys = array_merge(array_keys($found), array_keys($historicalFound));
        $missingRequired = array_diff_key($requiredSet, array_flip($foundKeys));

        $this->logger->log(
            [
                'message' => 'Batch collection completed',
                'found_count' => count($found) + count($historicalFound),
                'not_found_count' => count($missing),
                'required_satisfied' => $missingRequired === [],
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return new CollectBatchResult(
            found: $found,
            historicalFound: $historicalFound,
            notFound: array_values($missing),
            sourceAttempts: $allAttempts,
            requiredSatisfied: $missingRequired === [],
        );
    }

    /**
     * Try to extract datapoints from a single source.
     *
     * @param list<string> $keysToExtract
     * @return array{attempt: SourceAttempt, extractions: array<string, Extraction>, historicalExtractions: array<string, HistoricalExtraction>}
     */
    private function trySource(
        SourceCandidate $candidate,
        array $keysToExtract,
        CollectBatchRequest $request
    ): array {
        $attemptedAt = new DateTimeImmutable();
        $safeUrl = UrlSanitizer::sanitize($candidate->url);
        $extractions = [];
        $historicalExtractions = [];

        // Check cache first
        $fetchResult = $this->responseCache?->get($candidate->url);

        if ($fetchResult === null) {
            try {
                $fetchResult = $this->fetch($candidate);
                $this->responseCache?->set($candidate->url, $fetchResult);
            } catch (RateLimitException $e) {
                return [
                    'attempt' => new SourceAttempt(
                        url: $safeUrl,
                        providerId: $candidate->adapterId,
                        attemptedAt: $attemptedAt,
                        outcome: self::OUTCOME_RATE_LIMITED,
                        reason: $e->getMessage(),
                    ),
                    'extractions' => [],
                    'historicalExtractions' => [],
                ];
            } catch (BlockedException $e) {
                return [
                    'attempt' => new SourceAttempt(
                        url: $safeUrl,
                        providerId: $candidate->adapterId,
                        attemptedAt: $attemptedAt,
                        outcome: self::OUTCOME_BLOCKED,
                        reason: $e->getMessage(),
                    ),
                    'extractions' => [],
                    'historicalExtractions' => [],
                ];
            } catch (NetworkException $e) {
                $reason = str_contains(strtolower($e->getMessage()), 'timeout')
                    ? self::OUTCOME_TIMEOUT
                    : self::OUTCOME_NETWORK_ERROR;

                return [
                    'attempt' => new SourceAttempt(
                        url: $safeUrl,
                        providerId: $candidate->adapterId,
                        attemptedAt: $attemptedAt,
                        outcome: $reason,
                        reason: $e->getMessage(),
                    ),
                    'extractions' => [],
                    'historicalExtractions' => [],
                ];
            } catch (Throwable $e) {
                $this->logger->log(
                    [
                        'message' => 'Unexpected fetch error',
                        'url' => $safeUrl,
                        'error' => $e->getMessage(),
                    ],
                    Logger::LEVEL_ERROR,
                    'collection'
                );

                return [
                    'attempt' => new SourceAttempt(
                        url: $safeUrl,
                        providerId: $candidate->adapterId,
                        attemptedAt: $attemptedAt,
                        outcome: self::OUTCOME_NETWORK_ERROR,
                        reason: $e->getMessage(),
                    ),
                    'extractions' => [],
                    'historicalExtractions' => [],
                ];
            }
        }

        if ($fetchResult->statusCode >= 400) {
            return [
                'attempt' => new SourceAttempt(
                    url: $safeUrl,
                    providerId: $candidate->adapterId,
                    attemptedAt: $attemptedAt,
                    outcome: self::OUTCOME_HTTP_ERROR,
                    reason: "HTTP {$fetchResult->statusCode}",
                    httpStatus: $fetchResult->statusCode,
                ),
                'extractions' => [],
                'historicalExtractions' => [],
            ];
        }

        try {
            $adaptResult = $this->sourceAdapter->adapt(new AdaptRequest(
                fetchResult: $fetchResult,
                datapointKeys: $keysToExtract,
                ticker: $request->ticker,
            ));
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Adapter parse error',
                    'url' => $safeUrl,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );

            return [
                'attempt' => new SourceAttempt(
                    url: $safeUrl,
                    providerId: $candidate->adapterId,
                    attemptedAt: $attemptedAt,
                    outcome: self::OUTCOME_PARSE_FAILED,
                    reason: $e->getMessage(),
                    httpStatus: $fetchResult->statusCode,
                ),
                'extractions' => [],
                'historicalExtractions' => [],
            ];
        }

        // Collect all found extractions
        foreach ($keysToExtract as $key) {
            // Check for historical extraction first
            $historicalExtraction = $adaptResult->getHistoricalExtraction($key);
            if ($historicalExtraction !== null && !$historicalExtraction->isEmpty()) {
                // Apply freshness check if specified
                if (!$this->isHistoricalFresh($historicalExtraction, $request->asOfMin)) {
                    continue;
                }
                $historicalExtractions[$key] = $historicalExtraction;
                continue;
            }

            // Check for scalar extraction
            $extraction = $adaptResult->getExtraction($key);
            if ($extraction !== null) {
                // Apply freshness check if specified
                if (!$this->isFresh($extraction, $request->asOfMin)) {
                    continue;
                }
                $extractions[$key] = $extraction;
            }
        }

        $foundCount = count($extractions) + count($historicalExtractions);
        $requestedCount = count($keysToExtract);

        if ($foundCount === 0) {
            return [
                'attempt' => new SourceAttempt(
                    url: $safeUrl,
                    providerId: $candidate->adapterId,
                    attemptedAt: $attemptedAt,
                    outcome: self::OUTCOME_NOT_IN_PAGE,
                    reason: $adaptResult->parseError ?? 'No values found in page',
                    httpStatus: $fetchResult->statusCode,
                ),
                'extractions' => [],
                'historicalExtractions' => [],
            ];
        }

        $outcome = $foundCount === $requestedCount
            ? self::OUTCOME_SUCCESS
            : self::OUTCOME_PARTIAL;

        $this->logger->log(
            [
                'message' => 'Batch extraction completed',
                'url' => $safeUrl,
                'requested' => $requestedCount,
                'found' => $foundCount,
                'outcome' => $outcome,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return [
            'attempt' => new SourceAttempt(
                url: $safeUrl,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: $outcome,
                httpStatus: $fetchResult->statusCode,
            ),
            'extractions' => $extractions,
            'historicalExtractions' => $historicalExtractions,
        ];
    }

    private function fetch(SourceCandidate $candidate): FetchResult
    {
        return $this->webFetchClient->fetch(new FetchRequest(
            url: $candidate->url,
            headers: $candidate->headers,
        ));
    }

    /**
     * Group source candidates by URL to avoid duplicate fetches.
     *
     * @param list<SourceCandidate> $candidates
     * @return list<array{candidate: SourceCandidate}>
     */
    private function groupByUrl(array $candidates): array
    {
        $groups = [];
        $seenUrls = [];

        foreach ($candidates as $candidate) {
            // Skip duplicate URLs
            if (isset($seenUrls[$candidate->url])) {
                continue;
            }
            $seenUrls[$candidate->url] = true;

            $groups[] = ['candidate' => $candidate];
        }

        return $groups;
    }

    private function isFresh(Extraction $extraction, ?DateTimeImmutable $asOfMin): bool
    {
        if ($asOfMin === null) {
            return true;
        }

        if ($extraction->asOf === null) {
            return true;
        }

        return $extraction->asOf >= $asOfMin;
    }

    private function isHistoricalFresh(
        HistoricalExtraction $historicalExtraction,
        ?DateTimeImmutable $asOfMin
    ): bool {
        if ($asOfMin === null) {
            return true;
        }

        $mostRecentDate = $historicalExtraction->getMostRecentDate();
        if ($mostRecentDate === null) {
            return true;
        }

        return $mostRecentDate >= $asOfMin;
    }
}
