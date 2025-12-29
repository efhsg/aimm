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

    private const SOURCE_TEMPLATES = [
        'yahoo_finance' => [
            self::KEY_DOMAIN => 'finance.yahoo.com',
            self::KEY_PRIORITY => 1,
            self::KEY_URL_TEMPLATE => 'https://finance.yahoo.com/quote/{ticker}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => true,
        ],
        'yahoo_finance_api' => [
            self::KEY_DOMAIN => 'query1.finance.yahoo.com',
            self::KEY_PRIORITY => 2,
            self::KEY_URL_TEMPLATE => 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/{ticker}?modules=financialData,defaultKeyStatistics,price',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_YAHOO,
            self::KEY_SUPPORTS_MACRO => true,
        ],
        'stockanalysis' => [
            self::KEY_DOMAIN => 'stockanalysis.com',
            self::KEY_PRIORITY => 3,
            self::KEY_URL_TEMPLATE => 'https://stockanalysis.com/stocks/{ticker}/',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_LOWER,
            self::KEY_SUPPORTS_MACRO => false,
        ],
        'reuters' => [
            self::KEY_DOMAIN => 'www.reuters.com',
            self::KEY_PRIORITY => 4,
            self::KEY_URL_TEMPLATE => 'https://www.reuters.com/companies/{ticker}.{exchange}',
            self::KEY_TICKER_FORMAT => self::TICKER_FORMAT_REUTERS,
            self::KEY_SUPPORTS_MACRO => false,
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

        $symbolMap = [
            'macro.oil_price' => 'CL=F',
            'macro.gas_price' => 'NG=F',
            'macro.gold_price' => 'GC=F',
            'macro.sp500' => '^GSPC',
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
