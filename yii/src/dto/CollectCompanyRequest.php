<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Input DTO for collecting all data for a single company.
 */
final readonly class CollectCompanyRequest
{
    public function __construct(
        public string $ticker,
        public CompanyConfig $config,
        public DataRequirements $requirements,
        public int $maxDurationSeconds = 120,
        public ?SourcePriorities $sourcePriorities = null,
    ) {
    }
}
