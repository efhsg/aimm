<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Result of adapting fetched content.
 */
final readonly class AdaptResult
{
    /**
     * @param array<string, Extraction> $extractions
     * @param list<string> $notFound
     */
    public function __construct(
        public string $adapterId,
        public array $extractions,
        public array $notFound,
        public ?string $parseError = null,
    ) {
    }

    public function hasExtractions(): bool
    {
        return count($this->extractions) > 0;
    }

    public function getExtraction(string $key): ?Extraction
    {
        return $this->extractions[$key] ?? null;
    }
}
