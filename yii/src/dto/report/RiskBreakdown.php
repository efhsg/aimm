<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\Risk;

/**
 * Detailed risk assessment with factor scores.
 *
 * Composite score ranges from -1.0 (unacceptable) to +1.0 (acceptable).
 * Any single factor at "unacceptable" level overrides the composite.
 */
final readonly class RiskBreakdown
{
    /**
     * @param RiskFactor[] $factors
     */
    public function __construct(
        public Risk $assessment,
        public float $compositeScore,
        public array $factors,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'assessment' => $this->assessment->value,
            'composite_score' => round($this->compositeScore, 4),
            'factors' => array_map(
                static fn (RiskFactor $f): array => $f->toArray(),
                $this->factors
            ),
        ];
    }
}
