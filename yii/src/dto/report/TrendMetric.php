<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Single component of fundamentals scoring.
 *
 * Tracks YoY change for a metric and its contribution to the composite score.
 */
final readonly class TrendMetric
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $priorValue,
        public ?float $latestValue,
        public ?float $changePercent,
        public ?float $normalizedScore,
        public float $weight,
        public ?float $weightedScore,
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
            'prior_value' => $this->priorValue,
            'latest_value' => $this->latestValue,
            'change_percent' => $this->changePercent !== null
                ? round($this->changePercent, 2)
                : null,
            'normalized_score' => $this->normalizedScore !== null
                ? round($this->normalizedScore, 4)
                : null,
            'weight' => $this->weight,
            'weighted_score' => $this->weightedScore !== null
                ? round($this->weightedScore, 4)
                : null,
        ];
    }
}
