<?php

declare(strict_types=1);

namespace app\dto\datapoints;

use DateTimeImmutable;

/**
 * Records FX conversion details when a monetary value was converted.
 */
final readonly class FxConversion
{
    public function __construct(
        public string $originalCurrency,
        public float $originalValue,
        public float $rate,
        public DateTimeImmutable $rateAsOf,
        public string $rateSource,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_currency' => $this->originalCurrency,
            'original_value' => $this->originalValue,
            'rate' => $this->rate,
            'rate_as_of' => $this->rateAsOf->format('Y-m-d'),
            'rate_source' => $this->rateSource,
        ];
    }
}
