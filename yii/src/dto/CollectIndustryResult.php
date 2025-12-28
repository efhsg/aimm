<?php

declare(strict_types=1);

namespace app\dto;

use app\enums\CollectionStatus;

/**
 * Output DTO for industry collection.
 */
final readonly class CollectIndustryResult
{
    /**
     * @param array<string, CollectionStatus> $companyStatuses
     */
    public function __construct(
        public string $industryId,
        public string $datapackId,
        public string $dataPackPath,
        public GateResult $gateResult,
        public CollectionStatus $overallStatus,
        public array $companyStatuses,
    ) {
    }
}
