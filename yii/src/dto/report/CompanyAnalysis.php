<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\Rating;
use app\enums\RatingRulePath;

/**
 * Complete analysis of a single company in the ranked report.
 */
final readonly class CompanyAnalysis
{
    public function __construct(
        public string $ticker,
        public string $name,
        public Rating $rating,
        public RatingRulePath $rulePath,
        public ValuationSnapshot $valuation,
        public ValuationGapSummary $valuationGap,
        public FundamentalsBreakdown $fundamentals,
        public RiskBreakdown $risk,
        public int $rank,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'name' => $this->name,
            'rating' => $this->rating->value,
            'rule_path' => $this->rulePath->value,
            'valuation' => $this->valuation->toArray(),
            'valuation_gap' => $this->valuationGap->toArray(),
            'fundamentals' => $this->fundamentals->toArray(),
            'risk' => $this->risk->toArray(),
            'rank' => $this->rank,
        ];
    }
}
