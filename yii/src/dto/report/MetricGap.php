<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\GapDirection;

/**
 * Single valuation metric gap between company and group average.
 */
final readonly class MetricGap
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $companyValue,
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
            'company_value' => $this->companyValue !== null
                ? round($this->companyValue, 2)
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
