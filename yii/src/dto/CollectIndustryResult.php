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
        public int $runId,
        public string $industryId,
        public string $datapackId,
        public GateResult $gateResult,
        public CollectionStatus $overallStatus,
        public array $companyStatuses,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->overallStatus !== CollectionStatus::Failed;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return array_map(fn ($e) => $e->message, $this->gateResult->errors);
    }
}
