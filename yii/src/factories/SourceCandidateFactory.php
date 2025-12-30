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
    ) {
    }

    /**
     * Generate prioritized source candidates for a ticker.
     *
     * @return list<SourceCandidate>
     */
    public function forTicker(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
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
     * @return list<SourceCandidate>
     */
    public function forMacro(string $macroKey): array
    {
        $candidates = [];

        $macroKey = trim($macroKey);
        if ($macroKey === '') {
            return [];
        }

        $specialCandidates = $this->buildSpecialMacroCandidates($macroKey);
        if ($specialCandidates !== null) {
            return $specialCandidates;
        }

        $symbolMap = [
            'macro.oil_price' => self::SYMBOL_WTI,
            'macro.gas_price' => self::SYMBOL_NATURAL_GAS,
            'macro.gold_price' => self::SYMBOL_GOLD,
            'macro.sp500' => self::SYMBOL_SP500,
            'BRENT' => self::SYMBOL_BRENT,
            'WTI' => self::SYMBOL_WTI,
            'GOLD' => self::SYMBOL_GOLD,
            'XLE' => self::SYMBOL_XLE,
            'SP500' => self::SYMBOL_SP500,
            'SPX' => self::SYMBOL_SP500,
        ];

        $symbol = $symbolMap[$macroKey] ?? null;
        if ($symbol === null) {
            return [];
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
     * @return list<SourceCandidate>
     */
    public function forFinancials(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            if (!($config[self::KEY_SUPPORTS_FINANCIALS] ?? false)) {
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
     * Generate source candidates for quarterly financials data.
     *
     * @return list<SourceCandidate>
     */
    public function forQuarters(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            if (!($config[self::KEY_SUPPORTS_QUARTERS] ?? false)) {
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
}
