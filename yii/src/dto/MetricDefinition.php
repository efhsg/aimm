<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Defines a configurable metric and its unit/severity.
 */
final readonly class MetricDefinition
{
    public const UNIT_CURRENCY = 'currency';
    public const UNIT_RATIO = 'ratio';
    public const UNIT_PERCENT = 'percent';
    public const UNIT_NUMBER = 'number';

    public function __construct(
        public string $key,
        public string $unit,
        public bool $required = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'unit' => $this->unit,
            'required' => $this->required,
        ];
    }
}
