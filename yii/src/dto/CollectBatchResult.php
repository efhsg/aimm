<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Output DTO for batch datapoint collection.
 *
 * Contains all found extractions (scalar and historical), not-found keys,
 * and a flag indicating whether all required keys were satisfied.
 */
final readonly class CollectBatchResult
{
    /**
     * @param array<string, Extraction> $found Scalar extractions keyed by datapointKey
     * @param array<string, HistoricalExtraction> $historicalFound Historical extractions keyed by datapointKey
     * @param list<string> $notFound Keys that were not found
     * @param list<SourceAttempt> $sourceAttempts Full audit trail of all attempts
     */
    public function __construct(
        public array $found,
        public array $historicalFound,
        public array $notFound,
        public array $sourceAttempts,
        public bool $requiredSatisfied,
    ) {
    }

    /**
     * Check if a specific key was found (scalar or historical).
     */
    public function hasKey(string $key): bool
    {
        return isset($this->found[$key]) || isset($this->historicalFound[$key]);
    }

    /**
     * Get all found keys (both scalar and historical).
     *
     * @return list<string>
     */
    public function getFoundKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->found),
            array_keys($this->historicalFound)
        )));
    }
}
