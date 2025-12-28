<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\SourceCandidate;

/**
 * Factory for generating prioritized source candidates for a ticker.
 */
final class SourceCandidateFactory
{
    private const SOURCE_TEMPLATES = [
        'yahoo_finance' => [
            'domain' => 'finance.yahoo.com',
            'priority' => 1,
            'url_template' => 'https://finance.yahoo.com/quote/{ticker}',
        ],
        'yahoo_finance_api' => [
            'domain' => 'query1.finance.yahoo.com',
            'priority' => 2,
            'url_template' => 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/{ticker}?modules=financialData,defaultKeyStatistics',
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

    /**
     * Generate prioritized source candidates for a ticker.
     *
     * @return list<SourceCandidate>
     */
    public function forTicker(string $ticker, ?string $exchange = null): array
    {
        $candidates = [];
        $yahooTicker = $this->toYahooTicker($ticker, $exchange);

        foreach (self::SOURCE_TEMPLATES as $adapterId => $config) {
            $url = str_replace('{ticker}', urlencode($yahooTicker), $config['url_template']);

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $config['priority'],
                domain: $config['domain'],
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
            $url = str_replace('{ticker}', urlencode($symbol), $config['url_template']);

            $candidates[] = new SourceCandidate(
                url: $url,
                adapterId: $adapterId,
                priority: $config['priority'],
                domain: $config['domain'],
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
}
