<?php

declare(strict_types=1);

namespace app\validators;

/**
 * Domain-specific validation rules for semantic checks.
 */
final class SemanticValidationRules
{
    /**
     * Acceptable ranges for valuation metrics.
     * Values outside these ranges trigger warnings or errors based on severity.
     *
     * @var array<string, array{min: float, max: float, severity: string}>
     */
    public const RANGES = [
        'market_cap' => [
            'min' => 10_000_000,           // $10M minimum
            'max' => 10_000_000_000_000,   // $10T maximum
            'severity' => 'error',
        ],
        'fwd_pe' => [
            'min' => 0,
            'max' => 500,
            'severity' => 'warning',
        ],
        'trailing_pe' => [
            'min' => 0,
            'max' => 500,
            'severity' => 'warning',
        ],
        'ev_ebitda' => [
            'min' => -100,
            'max' => 200,
            'severity' => 'warning',
        ],
        'div_yield' => [
            'min' => 0,
            'max' => 25,
            'severity' => 'warning',
        ],
        'fcf_yield' => [
            'min' => -50,
            'max' => 50,
            'severity' => 'warning',
        ],
        'net_debt_ebitda' => [
            'min' => -10,
            'max' => 20,
            'severity' => 'warning',
        ],
        'price_to_book' => [
            'min' => 0,
            'max' => 100,
            'severity' => 'warning',
        ],
    ];

    /**
     * Cross-field consistency rules.
     *
     * @var array<string, array{description: string, tolerance?: float, severity?: string}>
     */
    public const CROSS_FIELD_RULES = [
        'fcf_yield_consistency' => [
            'description' => 'FCF yield should approximately equal FCF / market cap',
            'tolerance' => 0.20,  // 20% tolerance
        ],
        'pe_ratio_ordering' => [
            'description' => 'Forward P/E should typically be lower than trailing P/E for growing companies',
            'severity' => 'info',
        ],
    ];

    /**
     * Temporal sanity rules.
     *
     * @var array<string, int>
     */
    public const TEMPORAL_RULES = [
        'max_as_of_age_days' => 365,
        'max_future_as_of_days' => 1,
    ];

    /**
     * Allowed source URL hostnames (http/https only).
     * Non-http(s) schemes (e.g., cache://...) bypass this allowlist.
     *
     * @var list<string>
     */
    public const ALLOWED_DOMAINS = [
        'finance.yahoo.com',
        'query1.finance.yahoo.com',
        'www.reuters.com',
        'www.wsj.com',
        'www.bloomberg.com',
        'www.morningstar.com',
        'seekingalpha.com',
        'stockanalysis.com',
    ];
}
