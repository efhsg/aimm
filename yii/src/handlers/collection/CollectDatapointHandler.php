<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\adapters\SourceAdapterInterface;
use app\clients\FetchRequest;
use app\clients\WebFetchClientInterface;
use app\dto\AdaptRequest;
use app\dto\CollectDatapointRequest;
use app\dto\CollectDatapointResult;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\SourceAttempt;
use app\dto\SourceCandidate;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\exceptions\RateLimitException;
use app\factories\DataPointFactory;
use DateTimeImmutable;
use Throwable;
use yii\log\Logger;

/**
 * Collects a single datapoint from prioritized sources with full provenance.
 *
 * Iterates through source candidates, attempting fetch and adapt on each until
 * data is found or all sources are exhausted.
 */
final class CollectDatapointHandler implements CollectDatapointInterface
{
    private const OUTCOME_SUCCESS = 'success';
    private const OUTCOME_HTTP_ERROR = 'http_error';
    private const OUTCOME_PARSE_FAILED = 'parse_failed';
    private const OUTCOME_NOT_IN_PAGE = 'not_in_page';
    private const OUTCOME_RATE_LIMITED = 'rate_limited';
    private const OUTCOME_BLOCKED = 'blocked';
    private const OUTCOME_TIMEOUT = 'timeout';
    private const OUTCOME_NETWORK_ERROR = 'network_error';
    private const OUTCOME_STALE = 'stale';

    public function __construct(
        private readonly WebFetchClientInterface $webFetchClient,
        private readonly SourceAdapterInterface $sourceAdapter,
        private readonly DataPointFactory $dataPointFactory,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectDatapointRequest $request): CollectDatapointResult
    {
        $this->logger->log(
            [
                'message' => 'Starting datapoint collection',
                'datapoint_key' => $request->datapointKey,
                'source_count' => count($request->sourceCandidates),
                'ticker' => $request->ticker,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $sourceAttempts = [];

        foreach ($request->sourceCandidates as $candidate) {
            $attempt = $this->trySource($request, $candidate);
            $sourceAttempts[] = $attempt;

            if ($attempt->outcome === self::OUTCOME_SUCCESS) {
                return $this->buildSuccessResult($request, $sourceAttempts);
            }
        }

        return $this->buildNotFoundResult($request, $sourceAttempts);
    }

    private function trySource(
        CollectDatapointRequest $request,
        SourceCandidate $candidate
    ): SourceAttempt {
        $attemptedAt = new DateTimeImmutable();

        if ($this->webFetchClient->isRateLimited($candidate->domain)) {
            $this->logger->log(
                [
                    'message' => 'Source rate limited, skipping',
                    'url' => $candidate->url,
                    'domain' => $candidate->domain,
                ],
                Logger::LEVEL_INFO,
                'collection'
            );

            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_RATE_LIMITED,
                reason: 'Domain is currently rate limited',
            );
        }

        try {
            $fetchResult = $this->fetch($candidate);
        } catch (RateLimitException $e) {
            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_RATE_LIMITED,
                reason: $e->getMessage(),
            );
        } catch (BlockedException $e) {
            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_BLOCKED,
                reason: $e->getMessage(),
            );
        } catch (NetworkException $e) {
            $reason = str_contains(strtolower($e->getMessage()), 'timeout')
                ? self::OUTCOME_TIMEOUT
                : self::OUTCOME_NETWORK_ERROR;

            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: $reason,
                reason: $e->getMessage(),
            );
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Unexpected fetch error',
                    'url' => $candidate->url,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collection'
            );

            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_NETWORK_ERROR,
                reason: $e->getMessage(),
            );
        }

        if ($fetchResult->statusCode >= 400) {
            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_HTTP_ERROR,
                reason: "HTTP {$fetchResult->statusCode}",
                httpStatus: $fetchResult->statusCode,
            );
        }

        try {
            $adaptResult = $this->sourceAdapter->adapt(new AdaptRequest(
                fetchResult: $fetchResult,
                datapointKeys: [$request->datapointKey],
                ticker: $request->ticker,
            ));
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Adapter parse error',
                    'url' => $candidate->url,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );

            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_PARSE_FAILED,
                reason: $e->getMessage(),
                httpStatus: $fetchResult->statusCode,
            );
        }

        $extraction = $adaptResult->getExtraction($request->datapointKey);

        if ($extraction === null) {
            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_NOT_IN_PAGE,
                reason: $adaptResult->parseError ?? 'Value not found in page',
                httpStatus: $fetchResult->statusCode,
            );
        }

        if ($request->asOfMin !== null && !$this->isFresh($extraction, $request->asOfMin)) {
            return new SourceAttempt(
                url: $candidate->url,
                providerId: $candidate->adapterId,
                attemptedAt: $attemptedAt,
                outcome: self::OUTCOME_STALE,
                reason: sprintf(
                    'Data as-of %s is older than required %s',
                    $extraction->asOf?->format('Y-m-d') ?? 'unknown',
                    $request->asOfMin->format('Y-m-d')
                ),
                httpStatus: $fetchResult->statusCode,
            );
        }

        $this->storeExtractionForResult($candidate, $fetchResult, $extraction);

        $this->logger->log(
            [
                'message' => 'Successfully extracted datapoint',
                'datapoint_key' => $request->datapointKey,
                'url' => $candidate->url,
                'value' => $extraction->rawValue,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return new SourceAttempt(
            url: $candidate->url,
            providerId: $candidate->adapterId,
            attemptedAt: $attemptedAt,
            outcome: self::OUTCOME_SUCCESS,
            httpStatus: $fetchResult->statusCode,
        );
    }

    private function fetch(SourceCandidate $candidate): FetchResult
    {
        return $this->webFetchClient->fetch(new FetchRequest(
            url: $candidate->url,
        ));
    }

    private function isFresh(Extraction $extraction, DateTimeImmutable $asOfMin): bool
    {
        if ($extraction->asOf === null) {
            return true;
        }

        return $extraction->asOf >= $asOfMin;
    }

    /**
     * @var array{candidate: SourceCandidate, fetchResult: FetchResult, extraction: Extraction}|null
     */
    private ?array $lastSuccessfulExtraction = null;

    private function storeExtractionForResult(
        SourceCandidate $candidate,
        FetchResult $fetchResult,
        Extraction $extraction
    ): void {
        $this->lastSuccessfulExtraction = [
            'candidate' => $candidate,
            'fetchResult' => $fetchResult,
            'extraction' => $extraction,
        ];
    }

    /**
     * @param SourceAttempt[] $sourceAttempts
     */
    private function buildSuccessResult(
        CollectDatapointRequest $request,
        array $sourceAttempts
    ): CollectDatapointResult {
        if ($this->lastSuccessfulExtraction === null) {
            throw new \LogicException('Success result requested but no extraction stored');
        }

        $extraction = $this->lastSuccessfulExtraction['extraction'];
        $fetchResult = $this->lastSuccessfulExtraction['fetchResult'];

        $datapoint = $this->dataPointFactory->fromExtraction($extraction, $fetchResult);

        $this->lastSuccessfulExtraction = null;

        return new CollectDatapointResult(
            datapointKey: $request->datapointKey,
            datapoint: $datapoint,
            sourceAttempts: $sourceAttempts,
            found: true,
        );
    }

    /**
     * @param SourceAttempt[] $sourceAttempts
     */
    private function buildNotFoundResult(
        CollectDatapointRequest $request,
        array $sourceAttempts
    ): CollectDatapointResult {
        $attemptedSources = array_map(
            fn (SourceAttempt $attempt): string => sprintf(
                '%s (%s)',
                $attempt->url,
                $attempt->reason ?? $attempt->outcome
            ),
            $sourceAttempts
        );

        $unit = $this->inferUnitFromDatapointKey($request->datapointKey);
        $datapoint = $this->dataPointFactory->notFound($unit, $attemptedSources);

        $this->logger->log(
            [
                'message' => 'Datapoint not found after exhausting all sources',
                'datapoint_key' => $request->datapointKey,
                'attempted_sources' => count($sourceAttempts),
            ],
            Logger::LEVEL_WARNING,
            'collection'
        );

        return new CollectDatapointResult(
            datapointKey: $request->datapointKey,
            datapoint: $datapoint,
            sourceAttempts: $sourceAttempts,
            found: false,
        );
    }

    private function inferUnitFromDatapointKey(string $datapointKey): string
    {
        if (str_contains($datapointKey, 'market_cap') ||
            str_contains($datapointKey, 'revenue') ||
            str_contains($datapointKey, 'ebitda') ||
            str_contains($datapointKey, 'price')
        ) {
            return 'currency';
        }

        if (str_contains($datapointKey, '_pe') ||
            str_contains($datapointKey, 'ev_ebitda') ||
            str_contains($datapointKey, 'ratio')
        ) {
            return 'ratio';
        }

        if (str_contains($datapointKey, 'margin') ||
            str_contains($datapointKey, 'growth') ||
            str_contains($datapointKey, 'yield')
        ) {
            return 'percent';
        }

        return 'number';
    }
}
