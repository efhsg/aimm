<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\Rating;
use app\enums\RatingRulePath;

/**
 * Complete analysis of the focal company.
 */
final readonly class FocalAnalysis
{
    public function __construct(
        public Rating $rating,
        public RatingRulePath $rulePath,
        public ValuationSnapshot $valuation,
        public ValuationGapSummary $valuationGap,
        public FundamentalsBreakdown $fundamentals,
        public RiskBreakdown $risk,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rating' => $this->rating->value,
            'rule_path' => $this->rulePath->value,
            'valuation' => $this->valuation->toArray(),
            'valuation_gap' => $this->valuationGap->toArray(),
            'fundamentals' => $this->fundamentals->toArray(),
            'risk' => $this->risk->toArray(),
        ];
    }
}
