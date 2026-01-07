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
 * Adapter for Financial Modeling Prep (FMP) API JSON responses.
 *
 * Supports quote, key-metrics, ratios, income-statement, balance-sheet, and cash-flow endpoints.
 */
final class FmpAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'fmp';

    /**
     * Supported endpoint path segments keyed by adapter type.
     */
    private const ENDPOINT_SEGMENTS = [
        'quote' => 'quote',
        'key-metrics' => 'key-metrics',
        'ratios' => 'ratios',
        'income-statement' => 'income-statement',
        'cash-flow-statement' => 'cash-flow-statement',
        'balance-sheet-statement' => 'balance-sheet-statement',
    ];

    /**
     * Quote endpoint field mappings (scalar values).
     */
    private const QUOTE_FIELDS = [
        'valuation.market_cap' => ['field' => 'marketCap', 'unit' => 'currency'],
        'valuation.trailing_pe' => ['field' => 'pe', 'unit' => 'ratio'],
        'valuation.price_to_book' => ['field' => 'priceToBookRatio', 'unit' => 'ratio'],
        'macro.commodity_benchmark' => ['field' => 'price', 'unit' => 'currency'],
        'macro.margin_proxy' => ['field' => 'price', 'unit' => 'currency'],
        'macro.sector_index' => ['field' => 'price', 'unit' => 'number'],
    ];

    /**
     * Exchange to currency mapping for non-USD markets.
     */
    private const EXCHANGE_CURRENCY_MAP = [
        'AMS' => 'EUR',
        'EURONEXT' => 'EUR',
        'PAR' => 'EUR',
        'FRA' => 'EUR',
        'XETRA' => 'EUR',
        'LSE' => 'GBP',
        'LON' => 'GBP',
        'TYO' => 'JPY',
        'JPX' => 'JPY',
        'HKG' => 'HKD',
        'HKEX' => 'HKD',
        'TSX' => 'CAD',
        'ASX' => 'AUD',
        'SIX' => 'CHF',
    ];

    /**
     * Key-metrics endpoint field mappings (scalar values, uses index 0).
     * Field names match FMP stable API key-metrics endpoint response.
     */
    private const KEY_METRICS_FIELDS = [
        'valuation.ev_ebitda' => ['field' => 'evToEBITDA', 'unit' => 'ratio'],
        'valuation.fcf_yield' => ['field' => 'freeCashFlowYield', 'unit' => 'percent'],
        'valuation.div_yield' => ['field' => 'dividendYield', 'unit' => 'percent'],
        'valuation.net_debt_ebitda' => ['field' => 'netDebtToEBITDA', 'unit' => 'ratio'],
        'valuation.market_cap' => ['field' => 'marketCap', 'unit' => 'currency'],
        'valuation.enterprise_value' => ['field' => 'enterpriseValue', 'unit' => 'currency'],
    ];

    /**
     * Ratios endpoint field mappings (scalar values, uses index 0).
     */
    private const RATIOS_FIELDS = [
        'valuation.fwd_pe' => ['field' => 'priceEarningsRatio', 'unit' => 'ratio'],
    ];

    /**
     * Income statement field mappings (historical).
     */
    private const INCOME_STATEMENT_FIELDS = [
        'financials.revenue' => ['field' => 'revenue', 'unit' => 'currency'],
        'financials.gross_profit' => ['field' => 'grossProfit', 'unit' => 'currency'],
        'financials.operating_income' => ['field' => 'operatingIncome', 'unit' => 'currency'],
        'financials.ebitda' => ['field' => 'ebitda', 'unit' => 'currency'],
        'financials.net_income' => ['field' => 'netIncome', 'unit' => 'currency'],
        'financials.shares_outstanding' => ['field' => 'weightedAverageShsOut', 'unit' => 'number'],
        'quarters.revenue' => ['field' => 'revenue', 'unit' => 'currency'],
        'quarters.gross_profit' => ['field' => 'grossProfit', 'unit' => 'currency'],
        'quarters.operating_income' => ['field' => 'operatingIncome', 'unit' => 'currency'],
        'quarters.ebitda' => ['field' => 'ebitda', 'unit' => 'currency'],
        'quarters.net_income' => ['field' => 'netIncome', 'unit' => 'currency'],
    ];

    /**
     * Cash flow statement field mappings (historical).
     */
    private const CASH_FLOW_FIELDS = [
        'financials.free_cash_flow' => ['field' => 'freeCashFlow', 'unit' => 'currency'],
        'quarters.free_cash_flow' => ['field' => 'freeCashFlow', 'unit' => 'currency'],
        'valuation.free_cash_flow_ttm' => ['field' => 'freeCashFlow', 'unit' => 'currency', 'ttm' => true],
    ];

    /**
     * Balance sheet field mappings (historical).
     */
    private const BALANCE_SHEET_FIELDS = [
        'financials.total_equity' => ['field' => 'totalStockholdersEquity', 'unit' => 'currency'],
        'financials.total_debt' => ['field' => 'totalDebt', 'unit' => 'currency'],
        'financials.cash_and_equivalents' => ['field' => 'cashAndCashEquivalents', 'unit' => 'currency'],
        'financials.net_debt' => [
            'derived' => true,
            'fields' => ['totalDebt', 'cashAndCashEquivalents'],
            'unit' => 'currency',
        ],
    ];

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return array_unique(array_merge(
            array_keys(self::QUOTE_FIELDS),
            array_keys(self::KEY_METRICS_FIELDS),
            array_keys(self::RATIOS_FIELDS),
            array_keys(self::INCOME_STATEMENT_FIELDS),
            array_keys(self::CASH_FLOW_FIELDS),
            array_keys(self::BALANCE_SHEET_FIELDS),
        ));
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        if (!$request->fetchResult->isJson()) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'FMP adapter requires JSON content',
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

        // Detect FMP API error responses (rate limit, auth errors, etc.)
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
            'quote' => $this->adaptQuote($decoded, $request->datapointKeys),
            'key-metrics' => $this->adaptKeyMetrics($decoded, $request->datapointKeys),
            'ratios' => $this->adaptRatios($decoded, $request->datapointKeys),
            'income-statement' => $this->adaptIncomeStatement($decoded, $request->datapointKeys),
            'cash-flow-statement' => $this->adaptCashFlow($decoded, $request->datapointKeys),
            'balance-sheet-statement' => $this->adaptBalanceSheet($decoded, $request->datapointKeys),
            default => new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: "Unknown FMP endpoint type: {$endpointType}",
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
            $pattern = sprintf('~/%s(/|$)~', preg_quote($segment, '~'));
            if (preg_match($pattern, $path) === 1) {
                return $type;
            }
        }

        return 'unknown';
    }

    /**
     * @param list<string> $requestedKeys
     */
    private function adaptQuote(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data) || !isset($data[0])) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty quote response',
            );
        }

        $record = $data[0];
        $extractions = [];
        $notFound = [];

        // Determine currency from exchange (AEX companies are in EUR, etc.)
        $quoteCurrency = $this->resolveCurrencyFromExchange($record['exchange'] ?? null);

        foreach ($requestedKeys as $key) {
            if (!isset(self::QUOTE_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::QUOTE_FIELDS[$key];
            $field = $config['field'];
            $value = $this->extractNumericValue($record, $field);

            if ($value === null) {
                $notFound[] = $key;
                continue;
            }

            $currency = $config['unit'] === 'currency' ? $quoteCurrency : null;

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $value,
                unit: $config['unit'],
                currency: $currency,
                scale: 'units',
                asOf: null,
                locator: SourceLocator::json("\$[0].{$field}", (string) $value),
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * Resolve currency from exchange code.
     *
     * Returns USD as default for US exchanges, otherwise uses EXCHANGE_CURRENCY_MAP.
     */
    private function resolveCurrencyFromExchange(?string $exchange): string
    {
        if ($exchange === null || $exchange === '') {
            return 'USD';
        }

        $normalizedExchange = strtoupper(trim($exchange));

        return self::EXCHANGE_CURRENCY_MAP[$normalizedExchange] ?? 'USD';
    }

    /**
     * @param list<string> $requestedKeys
     */
    private function adaptKeyMetrics(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data) || !isset($data[0])) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty key-metrics response',
            );
        }

        $record = $data[0];
        $extractions = [];
        $notFound = [];
        $asOf = $this->parseDate($record['date'] ?? null);

        foreach ($requestedKeys as $key) {
            if (!isset(self::KEY_METRICS_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::KEY_METRICS_FIELDS[$key];
            $field = $config['field'];
            $value = $this->extractNumericValue($record, $field);

            if ($value === null) {
                $notFound[] = $key;
                continue;
            }

            // Convert decimal to percent for percent unit
            if ($config['unit'] === 'percent' && abs($value) < 1) {
                $value *= 100;
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $value,
                unit: $config['unit'],
                currency: null,
                scale: null,
                asOf: $asOf,
                locator: SourceLocator::json("\$[0].{$field}", (string) $value),
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * @param list<string> $requestedKeys
     */
    private function adaptRatios(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data) || !isset($data[0])) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty ratios response',
            );
        }

        $record = $data[0];
        $extractions = [];
        $notFound = [];
        $asOf = $this->parseDate($record['date'] ?? null);

        foreach ($requestedKeys as $key) {
            if (!isset(self::RATIOS_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::RATIOS_FIELDS[$key];
            $field = $config['field'];
            $value = $this->extractNumericValue($record, $field);

            if ($value === null) {
                $notFound[] = $key;
                continue;
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $value,
                unit: $config['unit'],
                currency: null,
                scale: null,
                asOf: $asOf,
                locator: SourceLocator::json("\$[0].{$field}", (string) $value),
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
        );
    }

    /**
     * @param list<string> $requestedKeys
     */
    private function adaptIncomeStatement(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty income-statement response',
            );
        }

        $historicalExtractions = [];
        $notFound = [];
        $currency = $data[0]['reportedCurrency'] ?? 'USD';

        foreach ($requestedKeys as $key) {
            if (!isset(self::INCOME_STATEMENT_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::INCOME_STATEMENT_FIELDS[$key];
            $field = $config['field'];
            $periods = $this->extractHistoricalPeriods($data, $field);

            if (empty($periods)) {
                $notFound[] = $key;
                continue;
            }

            $historicalExtractions[$key] = new HistoricalExtraction(
                datapointKey: $key,
                periods: $periods,
                unit: $config['unit'],
                locator: SourceLocator::json("\$[*].{$field}", "periods: " . count($periods)),
                currency: $currency,
                scale: 'units',
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: [],
            notFound: $notFound,
            historicalExtractions: $historicalExtractions,
        );
    }

    /**
     * @param list<string> $requestedKeys
     */
    private function adaptCashFlow(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty cash-flow-statement response',
            );
        }

        $extractions = [];
        $historicalExtractions = [];
        $notFound = [];
        $currency = $data[0]['reportedCurrency'] ?? 'USD';

        foreach ($requestedKeys as $key) {
            if (!isset(self::CASH_FLOW_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::CASH_FLOW_FIELDS[$key];
            $field = $config['field'];

            // Handle TTM (trailing twelve months) as scalar
            if ($config['ttm'] ?? false) {
                $ttmValue = $this->calculateTtm($data, $field);
                if ($ttmValue !== null) {
                    $extractions[$key] = new Extraction(
                        datapointKey: $key,
                        rawValue: $ttmValue,
                        unit: $config['unit'],
                        currency: $currency,
                        scale: 'units',
                        asOf: $this->parseDate($data[0]['date'] ?? null),
                        locator: SourceLocator::json("\$[0..3].{$field}", "TTM: {$ttmValue}"),
                    );
                } else {
                    $notFound[] = $key;
                }
                continue;
            }

            // Historical extraction
            $periods = $this->extractHistoricalPeriods($data, $field);

            if (empty($periods)) {
                $notFound[] = $key;
                continue;
            }

            $historicalExtractions[$key] = new HistoricalExtraction(
                datapointKey: $key,
                periods: $periods,
                unit: $config['unit'],
                locator: SourceLocator::json("\$[*].{$field}", "periods: " . count($periods)),
                currency: $currency,
                scale: 'units',
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
     * @param list<string> $requestedKeys
     */
    private function adaptBalanceSheet(array $data, array $requestedKeys): AdaptResult
    {
        if (empty($data)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $requestedKeys,
                parseError: 'Empty balance-sheet-statement response',
            );
        }

        $historicalExtractions = [];
        $notFound = [];
        $currency = $data[0]['reportedCurrency'] ?? 'USD';

        foreach ($requestedKeys as $key) {
            if (!isset(self::BALANCE_SHEET_FIELDS[$key])) {
                $notFound[] = $key;
                continue;
            }

            $config = self::BALANCE_SHEET_FIELDS[$key];

            // Handle derived fields (net_debt = totalDebt - cash)
            if ($config['derived'] ?? false) {
                $periods = $this->extractDerivedPeriods($data, $config['fields']);

                if (empty($periods)) {
                    $notFound[] = $key;
                    continue;
                }

                $historicalExtractions[$key] = new HistoricalExtraction(
                    datapointKey: $key,
                    periods: $periods,
                    unit: $config['unit'],
                    locator: SourceLocator::json(
                        "\$[*].(" . implode(' - ', $config['fields']) . ")",
                        "periods: " . count($periods)
                    ),
                    currency: $currency,
                    scale: 'units',
                );
                continue;
            }

            // Handle direct field mappings
            $field = $config['field'];
            $periods = $this->extractHistoricalPeriods($data, $field);

            if (empty($periods)) {
                $notFound[] = $key;
                continue;
            }

            $historicalExtractions[$key] = new HistoricalExtraction(
                datapointKey: $key,
                periods: $periods,
                unit: $config['unit'],
                locator: SourceLocator::json("\$[*].{$field}", "periods: " . count($periods)),
                currency: $config['unit'] === 'currency' ? $currency : null,
                scale: 'units',
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: [],
            notFound: $notFound,
            historicalExtractions: $historicalExtractions,
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function extractNumericValue(array $record, string $field): ?float
    {
        if (!isset($record[$field])) {
            return null;
        }

        $value = $record[$field];

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<PeriodValue>
     */
    private function extractHistoricalPeriods(array $records, string $field): array
    {
        $periods = [];

        foreach ($records as $record) {
            $date = $this->parseDate($record['date'] ?? null);
            if ($date === null) {
                continue;
            }

            $value = $this->extractNumericValue($record, $field);
            if ($value === null) {
                continue;
            }

            $periods[] = new PeriodValue(
                endDate: $date,
                value: $value,
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
     * Extract derived periods (e.g., net_debt = totalDebt - cash).
     *
     * @param list<array<string, mixed>> $records
     * @param list<string> $fields [minuend, subtrahend]
     * @return list<PeriodValue>
     */
    private function extractDerivedPeriods(array $records, array $fields): array
    {
        if (count($fields) !== 2) {
            return [];
        }

        $periods = [];
        [$minuend, $subtrahend] = $fields;

        foreach ($records as $record) {
            $date = $this->parseDate($record['date'] ?? null);
            if ($date === null) {
                continue;
            }

            $minuendValue = $this->extractNumericValue($record, $minuend);
            $subtrahendValue = $this->extractNumericValue($record, $subtrahend);

            if ($minuendValue === null || $subtrahendValue === null) {
                continue;
            }

            $periods[] = new PeriodValue(
                endDate: $date,
                value: $minuendValue - $subtrahendValue,
            );
        }

        usort(
            $periods,
            static fn (PeriodValue $a, PeriodValue $b): int =>
            $b->endDate->getTimestamp() <=> $a->endDate->getTimestamp()
        );

        return $periods;
    }

    /**
     * Calculate TTM (trailing twelve months) value from quarterly data.
     *
     * @param list<array<string, mixed>> $records
     */
    private function calculateTtm(array $records, string $field): ?float
    {
        $values = [];

        foreach (array_slice($records, 0, 4) as $record) {
            $value = $this->extractNumericValue($record, $field);
            if ($value === null) {
                return null;
            }
            $values[] = $value;
        }

        if (count($values) < 4) {
            return null;
        }

        return array_sum($values);
    }

    /**
     * Detect FMP API error responses.
     *
     * FMP returns errors as: {"Error Message": "Limit Reach..."} or {"Error": "..."}
     *
     * @param array<string, mixed> $decoded
     */
    private function detectApiError(array $decoded): ?string
    {
        // Check for "Error Message" key (rate limits, auth errors)
        if (isset($decoded['Error Message'])) {
            $error = (string) $decoded['Error Message'];

            if (str_contains($error, 'Limit Reach')) {
                return 'FMP API rate limit reached - daily quota exceeded';
            }

            return 'FMP API error: ' . $error;
        }

        // Check for "Error" key (other errors)
        if (isset($decoded['Error'])) {
            return 'FMP API error: ' . (string) $decoded['Error'];
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
