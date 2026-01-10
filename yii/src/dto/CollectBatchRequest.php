<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * Input DTO for batch datapoint collection.
 *
 * Collects multiple datapoints in a single operation with required/optional
 * distinction for early-exit optimization.
 */
final readonly class CollectBatchRequest
{
    /**
     * @param list<string> $datapointKeys All keys to collect (required + optional)
     * @param list<string> $requiredKeys Subset that must be found for early-exit
     * @param list<SourceCandidate> $sourceCandidates Priority-ordered sources
     */
    public function __construct(
        public array $datapointKeys,
        public array $requiredKeys,
        public array $sourceCandidates,
        public ?string $ticker = null,
        public ?DateTimeImmutable $asOfMin = null,
    ) {
    }
}
