<?php

declare(strict_types=1);

namespace app\dto\analysis;

/**
 * Thresholds for risk factor assessment.
 *
 * Factors:
 * - Leverage: Net Debt / EBITDA (lower is better)
 * - Liquidity: Cash / Total Debt (higher is better)
 * - FCF Coverage: FCF / Net Debt (higher is better)
 */
final readonly class RiskThresholds
{
    public function __construct(
        // Leverage: Net Debt / EBITDA
        public float $leverageAcceptable = 2.0,
        public float $leverageElevated = 4.0,
        public float $leverageWeight = 0.40,

        // Liquidity: Cash / Total Debt
        public float $liquidityAcceptable = 0.20,
        public float $liquidityElevated = 0.10,
        public float $liquidityWeight = 0.30,

        // FCF Coverage: FCF / Net Debt
        public float $fcfCoverageAcceptable = 0.15,
        public float $fcfCoverageElevated = 0.05,
        public float $fcfCoverageWeight = 0.30,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            leverageAcceptable: (float) ($data['leverage_acceptable'] ?? 2.0),
            leverageElevated: (float) ($data['leverage_elevated'] ?? 4.0),
            leverageWeight: (float) ($data['leverage_weight'] ?? 0.40),
            liquidityAcceptable: (float) ($data['liquidity_acceptable'] ?? 0.20),
            liquidityElevated: (float) ($data['liquidity_elevated'] ?? 0.10),
            liquidityWeight: (float) ($data['liquidity_weight'] ?? 0.30),
            fcfCoverageAcceptable: (float) ($data['fcf_coverage_acceptable'] ?? 0.15),
            fcfCoverageElevated: (float) ($data['fcf_coverage_elevated'] ?? 0.05),
            fcfCoverageWeight: (float) ($data['fcf_coverage_weight'] ?? 0.30),
        );
    }

    /**
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'leverage_acceptable' => $this->leverageAcceptable,
            'leverage_elevated' => $this->leverageElevated,
            'leverage_weight' => $this->leverageWeight,
            'liquidity_acceptable' => $this->liquidityAcceptable,
            'liquidity_elevated' => $this->liquidityElevated,
            'liquidity_weight' => $this->liquidityWeight,
            'fcf_coverage_acceptable' => $this->fcfCoverageAcceptable,
            'fcf_coverage_elevated' => $this->fcfCoverageElevated,
            'fcf_coverage_weight' => $this->fcfCoverageWeight,
        ];
    }
}
