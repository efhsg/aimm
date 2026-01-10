<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\SourceLocator;
use DateTimeImmutable;

/**
 * A single extracted datapoint from a source.
 */
final readonly class Extraction
{
    public function __construct(
        public string $datapointKey,
        public mixed $rawValue,
        public string $unit,
        public ?string $currency = null,
        public ?string $scale = null,
        public ?DateTimeImmutable $asOf = null,
        public SourceLocator $locator,
        public ?string $cacheSource = null,
        public ?int $cacheAgeDays = null,
        public ?string $providerId = null,
    ) {
    }

    public function isFromCache(): bool
    {
        return $this->cacheSource !== null;
    }

    /**
     * Returns a new Extraction with the given provider ID.
     */
    public function withProviderId(string $providerId): self
    {
        return new self(
            datapointKey: $this->datapointKey,
            rawValue: $this->rawValue,
            unit: $this->unit,
            currency: $this->currency,
            scale: $this->scale,
            asOf: $this->asOf,
            locator: $this->locator,
            cacheSource: $this->cacheSource,
            cacheAgeDays: $this->cacheAgeDays,
            providerId: $providerId,
        );
    }
}
