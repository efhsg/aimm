<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\HistoricalExtraction;
use app\dto\PeriodValue;
use DateTimeImmutable;

/**
 * Adapter for EODHD (EOD Historical Data) API responses.
 *
 * Supports dividends and splits endpoints for corporate actions data.
 */
final class EodhdAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'eodhd';

    /**
     * Supported endpoint path segments.
     */
    private const ENDPOINT_SEGMENTS = [
        'div' => 'dividends',
        'splits' => 'splits',
    ];

    /**
     * Dividend endpoint field mappings.
     *
     * Note: Dividend yield requires price data and should be derived downstream
     * by combining dividends.history with price from FMP/Yahoo.
     */
    private const DIVIDEND_FIELDS = [
        'dividends.history' => ['field' => 'value', 'unit' => 'currency', 'historical' => true],
        'dividends.annual_total' => ['field' => 'value', 'unit' => 'currency', 'aggregate' => 'annual_sum'],
        'dividends.latest' => ['field' => 'value', 'unit' => 'currency'],
        'dividends.ex_date' => ['field' => 'date', 'unit' => 'date'],
        'dividends.payment_date' => ['field' => 'paymentDate', 'unit' => 'date'],
        'dividends.record_date' => ['field' => 'recordDate', 'unit' => 'date'],
    ];

    /**
     * Split endpoint field mappings.
     */
    private const SPLIT_FIELDS = [
        'splits.history' => ['field' => 'split', 'unit' => 'ratio', 'historical' => true],
        'splits.latest' => ['field' => 'split', 'unit' => 'ratio'],
        'splits.latest_date' => ['field' => 'date', 'unit' => 'date'],
    ];

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return array_merge(
            array_keys(self::DIVIDEND_FIELDS),
            array_keys(self::SPLIT_FIELDS),
        );
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        if (!$request->fetchResult->isJson()) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'EODHD adapter requires JSON content',
            );
        }

        $decoded = json_decode($request->fetchResult->content, true);
        if (!is_array($decoded)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Invalid JSON response',
            );
        }

        // Detect API error responses
        $apiError = $this->detectApiError($decoded);
        if ($apiError !== null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: $apiError,
            );
        }

        $endpointType = $this->detectEndpointType($request->fetchResult->url);

        return match ($endpointType) {
            'dividends' => $this->adaptDividends($decoded, $request->datapointKeys),
            'splits' => $this->adaptSplits($decoded, $request->datapointKeys),
            default => new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: "Unknown EODHD endpoint type: {$endpointType}",
            ),
        };
    }

    private function detectEndpointType(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return 'unknown';
        }

        foreach (self::ENDPOINT_SEGMENTS as $segment => $type) {
            if (str_contains($path, "/api/{$segment}/")) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * Adapt dividends endpoint response.
     *
     * @param list<array<string, mixed>> $data
     * @param list<string> $requestedKeys
     */
    private function adaptDividends(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty dividends response',
            );
        }

        $extractions = [];
        $historicalExtractions = [];
        $notFound = [];

        // Detect currency from first record
        $currency = $data[0]['currency'] ?? 'USD';

        foreach ($requestedKeys as $key) {
            if (!isset(self::DIVIDEND_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::DIVIDEND_FIELDS[$key];

            // Handle historical dividend data
            if ($config['historical'] ?? false) {
                $periods = $this->extractDividendPeriods($data);

                if (empty($periods)) {
                    $notFound[] = $key;
                    continue;
                }

                $historicalExtractions[$key] = new HistoricalExtraction(
                    datapointKey: $key,
                    periods: $periods,
                    unit: $config['unit'],
                    locator: SourceLocator::json('$[*].value', "periods: " . count($periods)),
                    currency: $currency,
                    scale: 'units',
                    providerId: self::ADAPTER_ID,
                );
                continue;
            }

            // Handle annual sum aggregate (trailing 12 months)
            if (($config['aggregate'] ?? null) === 'annual_sum') {
                $annualTotal = $this->calculateAnnualDividendTotal($data);
                if ($annualTotal === null) {
                    $notFound[] = $key;
                    continue;
                }

                $asOf = $this->parseDate($data[0]['date'] ?? null);

                $extractions[$key] = new Extraction(
                    datapointKey: $key,
                    rawValue: $annualTotal,
                    unit: $config['unit'],
                    currency: $currency,
                    scale: 'units',
                    asOf: $asOf,
                    locator: SourceLocator::json('$[*].value', "annual sum: {$annualTotal}"),
                    providerId: self::ADAPTER_ID,
                );
                continue;
            }

            // Scalar values: use most recent dividend
            $latestRecord = $data[0];
            $field = $config['field'];

            if (!isset($latestRecord[$field])) {
                $notFound[] = $key;
                continue;
            }

            $value = $latestRecord[$field];
            $asOf = $this->parseDate($latestRecord['date'] ?? null);

            // Handle date fields
            if ($config['unit'] === 'date') {
                $dateValue = $this->parseDate($value);
                if ($dateValue === null) {
                    $notFound[] = $key;
                    continue;
                }
                $value = $dateValue->format('Y-m-d');
            } else {
                $value = is_numeric($value) ? (float) $value : null;
                if ($value === null) {
                    $notFound[] = $key;
                    continue;
                }
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $value,
                unit: $config['unit'],
                currency: $config['unit'] === 'currency' ? $currency : null,
                scale: 'units',
                asOf: $asOf,
                locator: SourceLocator::json("\$[0].{$field}", (string) $value),
                providerId: self::ADAPTER_ID,
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
            historicalExtractions: $historicalExtractions,
        );
    }

    /**
     * Adapt splits endpoint response.
     *
     * @param list<array<string, mixed>> $data
     * @param list<string> $requestedKeys
     */
    private function adaptSplits(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data)) {
            // Empty splits array is valid - means no splits occurred
            $extractions = [];
            $historicalExtractions = [];
            $notFound = [];

            foreach ($requestedKeys as $key) {
                if (!isset(self::SPLIT_FIELDS[$key])) {
                    $notFound[] = $key;
                    continue;
                }

                $config = self::SPLIT_FIELDS[$key];

                // For historical, return empty periods
                if ($config['historical'] ?? false) {
                    $historicalExtractions[$key] = new HistoricalExtraction(
                        datapointKey: $key,
                        periods: [],
                        unit: $config['unit'],
                        locator: SourceLocator::json('$[*].split', 'periods: 0'),
                        currency: null,
                        scale: null,
                        providerId: self::ADAPTER_ID,
                    );
                } else {
                    // Scalar values: no splits available
                    $notFound[] = $key;
                }
            }

            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: $extractions,
                notFound: $notFound,
                historicalExtractions: $historicalExtractions,
            );
        }

        $extractions = [];
        $historicalExtractions = [];
        $notFound = [];

        foreach ($requestedKeys as $key) {
            if (!isset(self::SPLIT_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::SPLIT_FIELDS[$key];

            // Handle historical split data
            if ($config['historical'] ?? false) {
                $periods = $this->extractSplitPeriods($data);

                $historicalExtractions[$key] = new HistoricalExtraction(
                    datapointKey: $key,
                    periods: $periods,
                    unit: $config['unit'],
                    locator: SourceLocator::json('$[*].split', "periods: " . count($periods)),
                    currency: null,
                    scale: null,
                    providerId: self::ADAPTER_ID,
                );
                continue;
            }

            // Scalar values: use most recent split
            $latestRecord = $data[0];
            $field = $config['field'];

            if (!isset($latestRecord[$field])) {
                $notFound[] = $key;
                continue;
            }

            $value = $latestRecord[$field];
            $asOf = $this->parseDate($latestRecord['date'] ?? null);

            // Handle date fields
            if ($config['unit'] === 'date') {
                $dateValue = $this->parseDate($value);
                if ($dateValue === null) {
                    $notFound[] = $key;
                    continue;
                }
                $value = $dateValue->format('Y-m-d');
            } else {
                // Split ratio comes as string like "2/1" or float
                $value = $this->parseSplitRatio($value);
                if ($value === null) {
                    $notFound[] = $key;
                    continue;
                }
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $value,
                unit: $config['unit'],
                currency: null,
                scale: null,
                asOf: $asOf,
                locator: SourceLocator::json("\$[0].{$field}", (string) $value),
                providerId: self::ADAPTER_ID,
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
            historicalExtractions: $historicalExtractions,
        );
    }

    /**
     * Extract dividend periods from response.
     *
     * @param list<array<string, mixed>> $records
     * @return list<PeriodValue>
     */
    private function extractDividendPeriods(array $records): array
    {
        $periods = [];

        foreach ($records as $record) {
            $date = $this->parseDate($record['date'] ?? null);
            if ($date === null) {
                continue;
            }

            $value = $record['value'] ?? null;
            if (!is_numeric($value)) {
                continue;
            }

            $periods[] = new PeriodValue(
                endDate: $date,
                value: (float) $value,
            );
        }

        // Sort by date descending (newest first)
        usort(
            $periods,
            static fn (PeriodValue $a, PeriodValue $b): int =>
            $b->endDate->getTimestamp() <=> $a->endDate->getTimestamp()
        );

        return $periods;
    }

    /**
     * Calculate trailing 12-month dividend total.
     *
     * Sums all dividends paid within the last 365 days from the most recent dividend date.
     *
     * @param list<array<string, mixed>> $records
     */
    private function calculateAnnualDividendTotal(array $records): ?float
    {
        if (empty($records)) {
            return null;
        }

        // Get the most recent dividend date as reference
        $latestDate = $this->parseDate($records[0]['date'] ?? null);
        if ($latestDate === null) {
            return null;
        }

        $oneYearAgo = $latestDate->modify('-1 year');
        $total = 0.0;

        foreach ($records as $record) {
            $date = $this->parseDate($record['date'] ?? null);
            if ($date === null) {
                continue;
            }

            // Only include dividends within the trailing 12 months
            if ($date < $oneYearAgo) {
                break; // Records are sorted newest first, so we can stop
            }

            $value = $record['value'] ?? null;
            if (is_numeric($value)) {
                $total += (float) $value;
            }
        }

        return $total > 0 ? $total : null;
    }

    /**
     * Extract split periods from response.
     *
     * @param list<array<string, mixed>> $records
     * @return list<PeriodValue>
     */
    private function extractSplitPeriods(array $records): array
    {
        $periods = [];

        foreach ($records as $record) {
            $date = $this->parseDate($record['date'] ?? null);
            if ($date === null) {
                continue;
            }

            $splitRatio = $this->parseSplitRatio($record['split'] ?? null);
            if ($splitRatio === null) {
                continue;
            }

            $periods[] = new PeriodValue(
                endDate: $date,
                value: $splitRatio,
            );
        }

        // Sort by date descending (newest first)
        usort(
            $periods,
            static fn (PeriodValue $a, PeriodValue $b): int =>
            $b->endDate->getTimestamp() <=> $a->endDate->getTimestamp()
        );

        return $periods;
    }

    /**
     * Parse split ratio from string or float.
     *
     * EODHD returns splits as "2/1" or similar fractions.
     */
    private function parseSplitRatio(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            $parts = explode('/', $value);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && (float) $parts[1] !== 0.0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return null;
    }

    /**
     * Detect EODHD API error responses.
     *
     * @param array<string, mixed>|list<mixed> $decoded
     */
    private function detectApiError(array $decoded): ?string
    {
        // Check for error message in response
        if (isset($decoded['error'])) {
            return 'EODHD API error: ' . (string) $decoded['error'];
        }

        // Check for rate limit message
        if (isset($decoded['message']) && str_contains((string) $decoded['message'], 'limit')) {
            return 'EODHD API rate limit reached';
        }

        return null;
    }

    private function parseDate(?string $dateString): ?DateTimeImmutable
    {
        if ($dateString === null || $dateString === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateString);

        return $date instanceof DateTimeImmutable ? $date : null;
    }
}
