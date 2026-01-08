<?php

declare(strict_types=1);

namespace app\dto\report;

use app\enums\Risk;

/**
 * Single component of risk assessment.
 *
 * Tracks a balance sheet ratio and its risk level.
 */
final readonly class RiskFactor
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $value,
        public Risk $level,
        public float $weight,
        public string $formula,
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
            'value' => $this->value !== null ? round($this->value, 4) : null,
            'level' => $this->level->value,
            'weight' => $this->weight,
            'formula' => $this->formula,
        ];
    }
}
