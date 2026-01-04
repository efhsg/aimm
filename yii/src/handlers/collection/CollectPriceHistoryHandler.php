<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectPriceHistoryRequest;
use app\dto\CollectPriceHistoryResult;
use app\dto\SourceAttempt;
use app\enums\CollectionStatus;
use app\queries\PriceHistoryQuery;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Yii;
use yii\log\Logger;

/**
 * Collects historical stock price data for a company.
 *
 * Uses FMP's historical-price-eod endpoint to fetch daily OHLCV data
 * and stores it in the price_history table with symbol_type='stock'.
 *
 * Note: This handler uses Guzzle directly instead of WebFetchClientInterface
 * because FMP is an authenticated API with its own rate limiting (not web scraping).
 * WebFetchClient is designed for HTML scraping with block detection and domain filtering,
 * which don't apply to authenticated API calls with API-key-based rate limits.
 */
final class CollectPriceHistoryHandler implements CollectPriceHistoryInterface
{
    private const FMP_BASE_URL = 'https://financialmodelingprep.com/stable';

    public function __construct(
        private readonly PriceHistoryQuery $priceQuery,
        private readonly Logger $logger,
        private readonly ?ClientInterface $httpClient = null,
    ) {
    }

    public function collect(CollectPriceHistoryRequest $request): CollectPriceHistoryResult
    {
        $this->logger->log(
            [
                'message' => 'Starting price history collection',
                'ticker' => $request->ticker,
                'from' => $request->from->format('Y-m-d'),
                'to' => $request->to->format('Y-m-d'),
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $attempts = [];

        // Get FMP API key
        $apiKey = Yii::$app->params['fmpApiKey'] ?? null;
        if ($apiKey === null || $apiKey === '') {
            return new CollectPriceHistoryResult(
                ticker: $request->ticker,
                recordsCollected: 0,
                recordsInserted: 0,
                sourceAttempts: $attempts,
                status: CollectionStatus::Failed,
                error: 'FMP API key not configured',
            );
        }

        // Fetch historical prices from FMP
        $url = $this->buildUrl($request, $apiKey);
        $fetchResult = $this->fetchPrices($url, $request->ticker);
        $attempts[] = $fetchResult['attempt'];

        if (!$fetchResult['success']) {
            return new CollectPriceHistoryResult(
                ticker: $request->ticker,
                recordsCollected: 0,
                recordsInserted: 0,
                sourceAttempts: $attempts,
                status: CollectionStatus::Failed,
                error: $fetchResult['error'] ?? 'Failed to fetch price history',
            );
        }

        $prices = $fetchResult['data'];
        if (empty($prices)) {
            return new CollectPriceHistoryResult(
                ticker: $request->ticker,
                recordsCollected: 0,
                recordsInserted: 0,
                sourceAttempts: $attempts,
                status: CollectionStatus::Complete,
            );
        }

        // Filter out existing dates
        $existingDates = $this->priceQuery->findExistingDates(
            $request->ticker,
            $request->from,
            $request->to
        );
        $existingLookup = array_flip($existingDates);

        // Prepare records for insertion
        $now = new DateTimeImmutable();
        $records = [];

        foreach ($prices as $price) {
            $date = $price['date'] ?? null;
            if ($date === null || isset($existingLookup[$date])) {
                continue;
            }

            $records[] = [
                'symbol' => $request->ticker,
                'symbol_type' => 'stock',
                'price_date' => $date,
                'open' => $price['open'] ?? null,
                'high' => $price['high'] ?? null,
                'low' => $price['low'] ?? null,
                'close' => $price['close'] ?? null,
                'adjusted_close' => $price['adjClose'] ?? $price['close'] ?? null,
                'volume' => $price['volume'] ?? null,
                'currency' => $request->currency,
                'source_adapter' => 'fmp',
                'collected_at' => $now->format('Y-m-d H:i:s'),
            ];
        }

        // Bulk insert new records
        $inserted = 0;
        if (!empty($records)) {
            try {
                $inserted = $this->priceQuery->bulkInsert($records);
            } catch (\Throwable $e) {
                $this->logger->log(
                    ['message' => 'Failed to insert price history', 'error' => $e->getMessage()],
                    Logger::LEVEL_ERROR,
                    'collection'
                );

                return new CollectPriceHistoryResult(
                    ticker: $request->ticker,
                    recordsCollected: count($prices),
                    recordsInserted: 0,
                    sourceAttempts: $attempts,
                    status: CollectionStatus::Failed,
                    error: 'Database insert failed: ' . $e->getMessage(),
                );
            }
        }

        $this->logger->log(
            [
                'message' => 'Price history collection complete',
                'ticker' => $request->ticker,
                'fetched' => count($prices),
                'inserted' => $inserted,
                'skipped_existing' => count($prices) - $inserted,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return new CollectPriceHistoryResult(
            ticker: $request->ticker,
            recordsCollected: count($prices),
            recordsInserted: $inserted,
            sourceAttempts: $attempts,
            status: CollectionStatus::Complete,
        );
    }

    private function buildUrl(CollectPriceHistoryRequest $request, string $apiKey): string
    {
        $params = http_build_query([
            'symbol' => $request->ticker,
            'from' => $request->from->format('Y-m-d'),
            'to' => $request->to->format('Y-m-d'),
            'apikey' => $apiKey,
        ]);

        return self::FMP_BASE_URL . '/historical-price-eod/full?' . $params;
    }

    /**
     * Fetch prices from FMP API.
     *
     * @return array{success: bool, data: list<array>, attempt: SourceAttempt, error?: string}
     */
    private function fetchPrices(string $url, string $ticker): array
    {
        $now = new DateTimeImmutable();
        $maskedUrl = $this->maskApiKey($url);

        try {
            $client = $this->httpClient ?? new Client(['timeout' => 30]);
            $response = $client->request('GET', $url);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return [
                    'success' => false,
                    'data' => [],
                    'error' => "HTTP {$statusCode}",
                    'attempt' => new SourceAttempt(
                        url: $maskedUrl,
                        providerId: 'fmp',
                        attemptedAt: $now,
                        outcome: 'http_error',
                        reason: "HTTP {$statusCode}",
                        httpStatus: $statusCode,
                    ),
                ];
            }

            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            // Check for API errors
            if (is_array($data) && isset($data['Error Message'])) {
                $error = (string) $data['Error Message'];
                $errorMsg = str_contains($error, 'Limit Reach')
                    ? 'FMP API rate limit exceeded'
                    : 'FMP API error: ' . $error;

                return [
                    'success' => false,
                    'data' => [],
                    'error' => $errorMsg,
                    'attempt' => new SourceAttempt(
                        url: $maskedUrl,
                        providerId: 'fmp',
                        attemptedAt: $now,
                        outcome: 'api_error',
                        reason: $errorMsg,
                        httpStatus: 200,
                    ),
                ];
            }

            // Historical endpoint returns array directly or nested under 'historical'
            $prices = [];
            if (is_array($data)) {
                if (isset($data['historical']) && is_array($data['historical'])) {
                    $prices = $data['historical'];
                } elseif (isset($data[0]['date'])) {
                    $prices = $data;
                }
            }

            return [
                'success' => true,
                'data' => $prices,
                'attempt' => new SourceAttempt(
                    url: $maskedUrl,
                    providerId: 'fmp',
                    attemptedAt: $now,
                    outcome: 'success',
                    httpStatus: 200,
                ),
            ];
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorMsg = $statusCode !== null ? "HTTP {$statusCode}" : $e->getMessage();

            return [
                'success' => false,
                'data' => [],
                'error' => $errorMsg,
                'attempt' => new SourceAttempt(
                    url: $maskedUrl,
                    providerId: 'fmp',
                    attemptedAt: $now,
                    outcome: 'http_error',
                    reason: $errorMsg,
                    httpStatus: $statusCode,
                ),
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'data' => [],
                'error' => 'Request failed: ' . $e->getMessage(),
                'attempt' => new SourceAttempt(
                    url: $maskedUrl,
                    providerId: 'fmp',
                    attemptedAt: $now,
                    outcome: 'exception',
                    reason: $e->getMessage(),
                ),
            ];
        }
    }

    private function maskApiKey(string $url): string
    {
        return preg_replace('/apikey=[^&]+/', 'apikey=***', $url) ?? $url;
    }
}
