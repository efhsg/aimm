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
    ) {
    }

    public function isFromCache(): bool
    {
        return $this->cacheSource !== null;
    }
}
