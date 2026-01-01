<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\SourceCandidate;

/**
 * Factory for generating prioritized source candidates for a ticker.
 */
final class SourceCandidateFactory
{
    private const KEY_DOMAIN = 'domain';
    private const KEY_PRIORITY = 'priority';
    private const KEY_URL_TEMPLATE = 'url_template';
    private const KEY_TICKER_FORMAT = 'ticker_format';
    private const KEY_SUPPORTS_MACRO = 'supports_macro';

    private const TICKER_FORMAT_YAHOO = 'yahoo';
    private const TICKER_FORMAT_LOWER = 'lower';
    private const TICKER_FORMAT_REUTERS = 'reuters';
    private const TICKER_FORMAT_UPPER = 'upper';

    private const KEY_SUPPORTS_FINANCIALS = 'supports_financials';
    private const KEY_SUPPORTS_QUARTERS = 'supports_quarters';

    private const MACRO_KEY_RIG_COUNT = 'rig_count';
    private const MACRO_KEY_INVENTORY = 'inventory';
    private const MACRO_KEY_OIL_INVENTORY = 'oil_inventory';

    // Yahoo Finance symbols (single source of truth)
    private const SYMBOL_WTI = 'CL=F';
    private const SYMBOL_BRENT = 'BZ=F';
    private const SYMBOL_NATURAL_GAS = 'NG=F';
    private const SYMBOL_GOLD = 'GC=F';
    private const SYMBOL_SP500 = '^GSPC';
    private const SYMBOL_XLE = 'XLE';

    private const EIA_INVENTORY_SERIES_DEFAULT = 'PET.WCRSTUS1.W';
    private const EIA_INVENTORY_URL_TEMPLATE = 'https://api.eia.gov/v2/seriesid/{series}?api_key={api_key}';

    // FMP API URL templates (API key passed via header, not URL)
    private const FMP_QUOTE_URL = 'https://financialmodelingprep.com/api/v3/quote/{ticker}';
    private const FMP_KEY_METRICS_URL = 'https://financialmodelingprep.com/api/v3/key-metrics/{ticker}';
    private const FMP_RATIOS_URL = 'https://financialmodelingprep.com/api/v3/ratios/{ticker}';
    private const FMP_INCOME_STATEMENT_URL = 'https://financialmodelingprep.com/api/v3/income-statement/{ticker}?period={period}';
    private const FMP_CASH_FLOW_URL = 'https://financialmodelingprep.com/api/v3/cash-flow-statement/{ticker}?period={period}';
    private const FMP_BALANCE_SHEET_URL = 'https://financialmodelingprep.com/api/v3/balance-sheet-statement/{ticker}?period={period}';

    // FMP API header for authentication
    private const FMP_API_HEADER = 'X-API-KEY';

    // ECB FX rate URL
    private const ECB_FX_RATES_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    // FMP commodity/index symbols
    private const FMP_SYMBOL_BRENT = 'BZUSD';
    private const FMP_SYMBOL_WTI = 'CLUSD';
    private const FMP_SYMBOL_XLE = 'XLE';

    private const SOURCE_TEMPLATES = [
        'yahoo_finance' => [
            self::KEY_DOMAIN => 'finance.yahoo.com',
            self::KEY_PRIORITY => 1,
            self::KEY_URL_TEMPLATE => 'https://finance.yahoo.com/quote/{ticker}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => true,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'yahoo_finance_api' => [
            self::KEY_DOMAIN => 'query1.finance.yahoo.com',
            self::KEY_PRIORITY => 2,
            self::KEY_URL_TEMPLATE => 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/{ticker}?modules=financialData,defaultKeyStatistics,price',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => true,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'yahoo_finance_financials' => [
            self::KEY_DOMAIN => 'query1.finance.yahoo.com',
            self::KEY_PRIORITY => 1,
            self::KEY_URL_TEMPLATE => 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/{ticker}?modules=incomeStatementHistory,balanceSheetHistory,cashflowStatementHistory,price',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => true,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'yahoo_finance_quarters' => [
            self::KEY_DOMAIN => 'query1.finance.yahoo.com',
            self::KEY_PRIORITY => 1,
            self::KEY_URL_TEMPLATE => 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/{ticker}?modules=incomeStatementHistoryQuarterly,cashflowStatementHistoryQuarterly,price',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => true,
        ],
        'stockanalysis' => [
            self::KEY_DOMAIN => 'stockanalysis.com',
            self::KEY_PRIORITY => 3,
            self::KEY_URL_TEMPLATE => 'https://stockanalysis.com/stocks/{ticker}/',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_LOWER,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'reuters' => [
            self::KEY_DOMAIN => 'www.reuters.com',
            self::KEY_PRIORITY => 4,
            self::KEY_URL_TEMPLATE => 'https://www.reuters.com/companies/{ticker}.{exchange}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_REUTERS,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'wsj' => [
            self::KEY_DOMAIN => 'www.wsj.com',
            self::KEY_PRIORITY => 5,
            self::KEY_URL_TEMPLATE => 'https://www.wsj.com/market-data/quotes/{ticker}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_UPPER,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'bloomberg' => [
            self::KEY_DOMAIN => 'www.bloomberg.com',
            self::KEY_PRIORITY => 6,
            self::KEY_URL_TEMPLATE => 'https://www.bloomberg.com/quote/{ticker}:US',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_UPPER,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'morningstar' => [
            self::KEY_DOMAIN => 'www.morningstar.com',
            self::KEY_PRIORITY => 7,
            self::KEY_URL_TEMPLATE => 'https://www.morningstar.com/stocks/{exchange}/{ticker}/quote',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_LOWER,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
        'seeking_alpha' => [
            self::KEY_DOMAIN => 'seekingalpha.com',
            self::KEY_PRIORITY => 8,
            self::KEY_URL_TEMPLATE => 'https://seekingalpha.com/symbol/{ticker}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_UPPER,
            self::KEY_SUPPORTS_MACRO => false,
            self::KEY_SUPPORTS_FINANCIALS => false,
            self::KEY_SUPPORTS_QUARTERS => false,
        ],
    ];

    private const EXCHANGE_SUFFIX_MAP = [
        'LSE' => '.L',
        'XLON' => '.L',
        'AMS' => '.AS',
        'XAMS' => '.AS',
        'FRA' => '.F',
        'XFRA' => '.F',
        'TYO' => '.T',
        'XTKS' => '.T',
        'HKG' => '.HK',
        'XHKG' => '.HK',
        'TSX' => '.TO',
        'XTSE' => '.TO',
        'ASX' => '.AX',
        'XASX' => '.AX',
    ];

    private const REUTERS_EXCHANGE_SUFFIX_MAP = [
        'NYSE' => 'N',
        'NASDAQ' => 'O',
        'LSE' => 'L',
        'XLON' => 'L',
        'AMS' => 'AS',
        'XAMS' => 'AS',
        'XPAR' => 'PA',
        'PAR' => 'PA',
        'FRA' => 'F',
        'XFRA' => 'F',
        'TYO' => 'T',
        'XTKS' => 'T',
        'HKG' => 'HK',
        'XHKG' => 'HK',
        'TSX' => 'TO',
        'XTSE' => 'TO',
    ];

    public function __construct(
        private readonly ?string $rigCountXlsxUrl = null,
        private readonly ?string $eiaApiKey = null,
        private readonly ?string $eiaInventorySeriesId = null,
        private readonly ?string $fmpApiKey = null,
    ) {
    }

    /**
     * Generate prioritized source candidates for a ticker.
     *
     * Per the Hybrid Strategy: Yahoo handles valuation (free, sufficient for daily updates).
     * FMP is reserved for financials/quarters to save API credits.
     *
     * @return list<SourceCandidate>
     */
    public function forTicker(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        // Note: FMP candidates are NOT added here for valuation.
        // Per design doc: "Yahoo for valuation to save credits".
        // FMP should only be used for financials/quarters via forFinancials()/forQuarters().

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            // forTicker() is used for single-point valuation scraping and must not include
            // the dedicated financials/quarters API endpoints (those are handled via
            // forFinancials()/forQuarters()) to avoid unnecessary requests and blocks.
            if (($config[self::KEY_SUPPORTS_FINANCIALS] ?? false) || ($config[self::KEY_SUPPORTS_QUARTERS] ?? false)) {
                continue;
            }

            $url = $this->buildUrl($config, $ticker, $exchange);
            if ($url === null) {
                continue;
            }

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $config[self::KEY_PRIORITY],
                domain: $config[self::KEY_DOMAIN],
            );
        }

        usort(
            $candidates,
            static fn (SourceCandidate $a, SourceCandidate $b): int =>
            $a->priority <=> $b->priority
        );

        return $candidates;
    }

    /**
     * Generate source candidates for macro/commodity data.
     *
     * Per the Hybrid Strategy: Yahoo handles commodity prices (free, daily updates).
     * ECB handles FX rates.
     *
     * @return list<SourceCandidate>
     */
    public function forMacro(string $macroKey): array
    {
        $candidates = [];

        $macroKey = trim($macroKey);
        if ($macroKey === '') {
            return [];
        }

        // Handle ECB FX rates
        if ($macroKey === 'macro.fx_rates') {
            return $this->buildEcbCandidates();
        }

        $specialCandidates = $this->buildSpecialMacroCandidates($macroKey);
        if ($specialCandidates !== null) {
            return $specialCandidates;
        }

        // Note: FMP candidates are NOT added here for macro data.
        // Per design doc: "Yahoo for valuation [and commodity prices] to save credits".
        // FMP should only be used for financials/quarters.

        $symbolMap = [
            'macro.oil_price' => self::SYMBOL_WTI,
            'macro.gas_price' => self::SYMBOL_NATURAL_GAS,
            'macro.gold_price' => self::SYMBOL_GOLD,
            'macro.sp500' => self::SYMBOL_SP500,
            'macro.commodity_benchmark' => self::SYMBOL_BRENT,
            'macro.margin_proxy' => self::SYMBOL_BRENT,
            'macro.sector_index' => self::SYMBOL_XLE,
            'brent_crude' => self::SYMBOL_BRENT,
            'BRENT' => self::SYMBOL_BRENT,
            'WTI' => self::SYMBOL_WTI,
            'wti_crude' => self::SYMBOL_WTI,
            'natural_gas' => self::SYMBOL_NATURAL_GAS,
            'GOLD' => self::SYMBOL_GOLD,
            'XLE' => self::SYMBOL_XLE,
            'SP500' => self::SYMBOL_SP500,
            'SPX' => self::SYMBOL_SP500,
        ];

        $symbol = $symbolMap[$macroKey] ?? null;
        if ($symbol === null) {
            return $candidates;
        }

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            if (!($config[self::KEY_SUPPORTS_MACRO] ?? false)) {
                continue;
            }

            $url = $this->buildUrl($config, $symbol, null);
            if ($url === null) {
                continue;
            }

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $config[self::KEY_PRIORITY],
                domain: $config[self::KEY_DOMAIN],
            );
        }

        usort(
            $candidates,
            static fn (SourceCandidate $a, SourceCandidate $b): int =>
            $a->priority <=> $b->priority
        );

        return $candidates;
    }

    /**
     * Build ECB source candidates for FX rates.
     *
     * @return list<SourceCandidate>
     */
    private function buildEcbCandidates(): array
    {
        return [
            new SourceCandidate(
                url: self::ECB_FX_RATES_URL,
                adapterId: 'ecb',
                priority: 1,
                domain: 'www.ecb.europa.eu',
            ),
        ];
    }

    /**
     * @return list<SourceCandidate>|null
     */
    private function buildSpecialMacroCandidates(string $macroKey): ?array
    {
        if ($macroKey === self::MACRO_KEY_RIG_COUNT) {
            return $this->buildRigCountCandidates();
        }

        if ($macroKey === self::MACRO_KEY_INVENTORY || $macroKey === self::MACRO_KEY_OIL_INVENTORY) {
            return $this->buildInventoryCandidates();
        }

        return null;
    }

    /**
     * @return list<SourceCandidate>
     */
    private function buildRigCountCandidates(): array
    {
        if ($this->rigCountXlsxUrl === null || trim($this->rigCountXlsxUrl) === '') {
            return [];
        }

        $candidate = $this->buildDirectCandidate(
            $this->rigCountXlsxUrl,
            'baker_hughes_rig_count',
            1
        );

        return $candidate === null ? [] : [$candidate];
    }

    /**
     * @return list<SourceCandidate>
     */
    private function buildInventoryCandidates(): array
    {
        $apiKey = $this->eiaApiKey ?? 'DEMO_KEY';
        if (trim($apiKey) === '') {
            return [];
        }

        $seriesId = $this->eiaInventorySeriesId ?? self::EIA_INVENTORY_SERIES_DEFAULT;
        if (trim($seriesId) === '') {
            return [];
        }

        $url = str_replace(
            ['{series}', '{api_key}'],
            [urlencode($seriesId), urlencode($apiKey)],
            self::EIA_INVENTORY_URL_TEMPLATE
        );

        $candidate = $this->buildDirectCandidate($url, 'eia_inventory', 1);

        return $candidate === null ? [] : [$candidate];
    }

    private function buildDirectCandidate(
        string $url,
        string $adapterId,
        int $priority
    ): ?SourceCandidate {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        return new SourceCandidate(
            url: $url,
            adapterId: $adapterId,
            priority: $priority,
            domain: $host,
        );
    }

    /**
     * Generate source candidates for annual financials data.
     *
     * Per the Hybrid Strategy: FMP handles historical financials (20+ years).
     * Yahoo is fallback if FMP fails.
     *
     * @return list<SourceCandidate>
     */
    public function forFinancials(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        // Add FMP candidates first (priority 1) - primary source for financials
        $fmpCandidates = $this->buildFmpFinancialsCandidates($ticker, 'annual');
        $candidates = array_merge($candidates, $fmpCandidates);

        // Add Yahoo as fallback (priority 2+)
        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            if (!($config[self::KEY_SUPPORTS_FINANCIALS] ?? false)) {
                continue;
            }

            $url = $this->buildUrl($config, $ticker, $exchange);
            if ($url === null) {
                continue;
            }

            // Yahoo gets higher priority number (lower priority) as FMP fallback
            $priority = $config[self::KEY_PRIORITY];
            if ($this->fmpApiKey !== null) {
                $priority += 5;
            }

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $priority,
                domain: $config[self::KEY_DOMAIN],
            );
        }

        usort(
            $candidates,
            static fn (SourceCandidate $a, SourceCandidate $b): int =>
            $a->priority <=> $b->priority
        );

        return $candidates;
    }

    /**
     * Generate source candidates for quarterly financials data.
     *
     * Per the Hybrid Strategy: FMP handles historical quarters (20+ years).
     * Yahoo is fallback if FMP fails.
     *
     * @return list<SourceCandidate>
     */
    public function forQuarters(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        // Add FMP candidates first (priority 1) - primary source for quarters
        $fmpCandidates = $this->buildFmpFinancialsCandidates($ticker, 'quarter');
        $candidates = array_merge($candidates, $fmpCandidates);

        // Add Yahoo as fallback (priority 2+)
        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            if (!($config[self::KEY_SUPPORTS_QUARTERS] ?? false)) {
                continue;
            }

            $url = $this->buildUrl($config, $ticker, $exchange);
            if ($url === null) {
                continue;
            }

            // Yahoo gets higher priority number (lower priority) as FMP fallback
            $priority = $config[self::KEY_PRIORITY];
            if ($this->fmpApiKey !== null) {
                $priority += 5;
            }

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $priority,
                domain: $config[self::KEY_DOMAIN],
            );
        }

        usort(
            $candidates,
            static fn (SourceCandidate $a, SourceCandidate $b): int =>
            $a->priority <=> $b->priority
        );

        return $candidates;
    }

    private function toYahooTicker(string $ticker, ?string $exchange): string
    {
        if ($exchange === null) {
            return $ticker;
        }

        $suffix = self::EXCHANGE_SUFFIX_MAP[$exchange] ?? null;
        if ($suffix === null) {
            return $ticker;
        }

        if (str_ends_with($ticker, $suffix)) {
            return $ticker;
        }

        return $ticker . $suffix;
    }

    private function buildUrl(
        array $config,
        string $ticker,
        ?string $exchange
    ): ?string {
        $urlTemplate = $config[self::KEY_URL_TEMPLATE];
        $normalizedTicker = $this->normalizeTicker(
            $config[self::KEY_TICKER_FORMAT] ?? null,
            $ticker,
            $exchange
        );

        if ($normalizedTicker === null) {
            return null;
        }

        $url = str_replace('{ticker}', urlencode($normalizedTicker), $urlTemplate);

        if (str_contains($urlTemplate, '{exchange}')) {
            $exchangeSuffix = $this->toReutersExchangeSuffix($exchange);
            if ($exchangeSuffix === null) {
                return null;
            }
            $url = str_replace('{exchange}', $exchangeSuffix, $url);
        }

        return $url;
    }

    private function normalizeTicker(?string $format, string $ticker, ?string $exchange): ?string
    {
        return match ($format) {
            self::TICKER_FORMAT_YAHOO => $this->toYahooTicker($ticker, $exchange),
            self::TICKER_FORMAT_LOWER => strtolower($ticker),
            self::TICKER_FORMAT_UPPER => strtoupper($ticker),
            self::TICKER_FORMAT_REUTERS => $ticker,
            null => $ticker,
            default => null,
        };
    }

    private function toReutersExchangeSuffix(?string $exchange): ?string
    {
        if ($exchange === null) {
            return null;
        }

        return self::REUTERS_EXCHANGE_SUFFIX_MAP[$exchange] ?? null;
    }

    /**
     * Build FMP candidates for financial statements.
     *
     * API key is passed via header (X-API-KEY), not in URL, to avoid credential exposure in logs.
     *
     * @return list<SourceCandidate>
     */
    private function buildFmpFinancialsCandidates(string $ticker, string $period): array
    {
        if ($this->fmpApiKey === null || $this->fmpApiKey === '') {
            return [];
        }

        $candidates = [];
        $upperTicker = strtoupper($ticker);
        $headers = [self::FMP_API_HEADER => $this->fmpApiKey];

        // Income statement
        $incomeUrl = $this->buildFmpUrl(
            str_replace('{period}', $period, self::FMP_INCOME_STATEMENT_URL),
            $upperTicker
        );
        if ($incomeUrl !== null) {
            $candidates[] = new SourceCandidate(
                url: $incomeUrl,
                adapterId: 'fmp',
                priority: 1,
                domain: 'financialmodelingprep.com',
                headers: $headers,
            );
        }

        // Cash flow statement
        $cashFlowUrl = $this->buildFmpUrl(
            str_replace('{period}', $period, self::FMP_CASH_FLOW_URL),
            $upperTicker
        );
        if ($cashFlowUrl !== null) {
            $candidates[] = new SourceCandidate(
                url: $cashFlowUrl,
                adapterId: 'fmp',
                priority: 1,
                domain: 'financialmodelingprep.com',
                headers: $headers,
            );
        }

        // Balance sheet (for net debt derivation)
        $balanceUrl = $this->buildFmpUrl(
            str_replace('{period}', $period, self::FMP_BALANCE_SHEET_URL),
            $upperTicker
        );
        if ($balanceUrl !== null) {
            $candidates[] = new SourceCandidate(
                url: $balanceUrl,
                adapterId: 'fmp',
                priority: 1,
                domain: 'financialmodelingprep.com',
                headers: $headers,
            );
        }

        return $candidates;
    }

    /**
     * Build FMP URL without API key (key is passed via header).
     */
    private function buildFmpUrl(string $template, string $ticker): ?string
    {
        if ($this->fmpApiKey === null || $this->fmpApiKey === '') {
            return null;
        }

        return str_replace('{ticker}', urlencode($ticker), $template);
    }
}
