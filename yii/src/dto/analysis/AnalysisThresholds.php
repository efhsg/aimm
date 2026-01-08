<?php

declare(strict_types=1);

namespace app\dto\analysis;

/**
 * Master threshold configuration for analysis.
 *
 * Configurable per collection policy to enable sector-specific analysis.
 */
final readonly class AnalysisThresholds
{
    public function __construct(
        public float $buyGapThreshold = 15.0,
        public float $fairValueThreshold = 5.0,
        public int $minMetricsForGap = 2,
        public FundamentalsWeights $fundamentalsWeights = new FundamentalsWeights(),
        public RiskThresholds $riskThresholds = new RiskThresholds(),
    ) {
    }

    /**
     * Create from collection policy JSON.
     *
     * @param array<string, mixed>|null $policyJson
     */
    public static function fromPolicy(?array $policyJson): self
    {
        if ($policyJson === null) {
            return new self();
        }

        return new self(
            buyGapThreshold: (float) ($policyJson['buy_gap_threshold'] ?? 15.0),
            fairValueThreshold: (float) ($policyJson['fair_value_threshold'] ?? 5.0),
            minMetricsForGap: (int) ($policyJson['min_metrics_for_gap'] ?? 2),
            fundamentalsWeights: FundamentalsWeights::fromArray(
                $policyJson['fundamentals_weights'] ?? []
            ),
            riskThresholds: RiskThresholds::fromArray(
                $policyJson['risk_thresholds'] ?? []
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'buy_gap_threshold' => $this->buyGapThreshold,
            'fair_value_threshold' => $this->fairValueThreshold,
            'min_metrics_for_gap' => $this->minMetricsForGap,
            'fundamentals_weights' => $this->fundamentalsWeights->toArray(),
            'risk_thresholds' => $this->riskThresholds->toArray(),
        ];
    }
}
