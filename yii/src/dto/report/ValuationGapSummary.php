<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\GapDirection;

/**
 * Valuation gap analysis vs peers.
 *
 * Contains composite gap (average of individual gaps) and individual metric gaps.
 */
final readonly class ValuationGapSummary
{
    /**
     * @param MetricGap[] $individualGaps
     */
    public function __construct(
        public ?float $compositeGap,
        public ?GapDirection $direction,
        public array $individualGaps,
        public int $metricsUsed,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'composite_gap' => $this->compositeGap !== null
                ? round($this->compositeGap, 2)
                : null,
            'direction' => $this->direction?->value,
            'individual_gaps' => array_map(
                static fn (MetricGap $g): array => $g->toArray(),
                $this->individualGaps
            ),
            'metrics_used' => $this->metricsUsed,
        ];
    }
}
