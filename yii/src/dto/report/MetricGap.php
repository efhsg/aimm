<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\GapDirection;

/**
 * Single valuation metric gap between focal and peer average.
 */
final readonly class MetricGap
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $focalValue,
        public ?float $peerAverage,
        public ?float $gapPercent,
        public ?GapDirection $direction,
        public string $interpretation,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'focal_value' => $this->focalValue !== null
                ? round($this->focalValue, 2)
                : null,
            'peer_average' => $this->peerAverage !== null
                ? round($this->peerAverage, 2)
                : null,
            'gap_percent' => $this->gapPercent !== null
                ? round($this->gapPercent, 2)
                : null,
            'direction' => $this->direction?->value,
            'interpretation' => $this->interpretation,
        ];
    }
}
