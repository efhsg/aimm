<?php

declare(strict_types=1);

namespace app\dto;

use app\enums\CollectionStatus;

/**
 * Output DTO for company data collection.
 */
final readonly class CollectCompanyResult
{
    /**
     * @param SourceAttempt[] $sourceAttempts All fetch attempts made during collection
     */
    public function __construct(
        public string $ticker,
        public CompanyData $data,
        public array $sourceAttempts,
        public CollectionStatus $status,
    ) {
    }
}
