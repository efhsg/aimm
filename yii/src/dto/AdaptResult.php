<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Result of adapting fetched content.
 */
final readonly class AdaptResult
{
    /**
     * @param array<string, Extraction> $extractions Scalar value extractions
     * @param array<string, HistoricalExtraction> $historicalExtractions Period-based historical extractions
     * @param list<string> $notFound
     */
    public function __construct(
        public string $adapterId,
        public array $extractions,
        public array $notFound,
        public ?string $parseError = null,
        public array $historicalExtractions = [],
    ) {
    }

    public function hasExtractions(): bool
    {
        return count($this->extractions) > 0 || count($this->historicalExtractions) > 0;
    }

    public function getExtraction(string $key): ?Extraction
    {
        return $this->extractions[$key] ?? null;
    }

    public function getHistoricalExtraction(string $key): ?HistoricalExtraction
    {
        return $this->historicalExtractions[$key] ?? null;
    }

    public function isHistorical(string $key): bool
    {
        return isset($this->historicalExtractions[$key]);
    }
}
