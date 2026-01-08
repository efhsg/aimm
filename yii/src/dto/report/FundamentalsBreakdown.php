<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\Fundamentals;

/**
 * Detailed fundamentals assessment with component scores.
 *
 * Composite score ranges from -1.0 (deteriorating) to +1.0 (improving).
 */
final readonly class FundamentalsBreakdown
{
    /**
     * @param TrendMetric[] $components
     */
    public function __construct(
        public Fundamentals $assessment,
        public float $compositeScore,
        public array $components,
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
            'components' => array_map(
                static fn (TrendMetric $c): array => $c->toArray(),
                $this->components
            ),
        ];
    }
}
