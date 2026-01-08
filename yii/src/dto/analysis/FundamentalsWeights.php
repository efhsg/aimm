<?php

declare(strict_types=1);

namespace app\dto\analysis;

/**
 * Weights and thresholds for fundamentals scoring.
 *
 * Components:
 * - Revenue Growth: YoY change in revenue
 * - Margin Expansion: Change in EBITDA margin (percentage points)
 * - FCF Trend: YoY change in free cash flow
 * - Debt Reduction: YoY reduction in net debt
 */
final readonly class FundamentalsWeights
{
    public function __construct(
        public float $revenueGrowthWeight = 0.30,
        public float $marginExpansionWeight = 0.25,
        public float $fcfTrendWeight = 0.25,
        public float $debtReductionWeight = 0.20,
        public float $improvingThreshold = 0.30,
        public float $deterioratingThreshold = -0.30,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            revenueGrowthWeight: (float) ($data['revenue_growth_weight'] ?? 0.30),
            marginExpansionWeight: (float) ($data['margin_expansion_weight'] ?? 0.25),
            fcfTrendWeight: (float) ($data['fcf_trend_weight'] ?? 0.25),
            debtReductionWeight: (float) ($data['debt_reduction_weight'] ?? 0.20),
            improvingThreshold: (float) ($data['improving_threshold'] ?? 0.30),
            deterioratingThreshold: (float) ($data['deteriorating_threshold'] ?? -0.30),
        );
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'revenue_growth_weight' => $this->revenueGrowthWeight,
            'margin_expansion_weight' => $this->marginExpansionWeight,
            'fcf_trend_weight' => $this->fcfTrendWeight,
            'debt_reduction_weight' => $this->debtReductionWeight,
            'improving_threshold' => $this->improvingThreshold,
            'deteriorating_threshold' => $this->deterioratingThreshold,
        ];
    }
}
