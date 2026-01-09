<?php

declare(strict_types=1);

namespace app\dto\industry;

use DateTimeImmutable;

/**
 * Response DTO for a single industry with stats.
 */
final readonly class IndustryResponse
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $sectorId,
        public string $sectorSlug,
        public string $sectorName,
        public ?string $description,
        public ?int $policyId,
        public ?string $policyName,
        public bool $isActive,
        public int $companyCount,
        public ?string $lastRunStatus,
        public ?DateTimeImmutable $lastRunAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $createdBy,
        public ?string $updatedBy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'sector_id' => $this->sectorId,
            'sector_slug' => $this->sectorSlug,
            'sector_name' => $this->sectorName,
            'description' => $this->description,
            'policy_id' => $this->policyId,
            'policy_name' => $this->policyName,
            'is_active' => $this->isActive,
            'company_count' => $this->companyCount,
            'last_run_status' => $this->lastRunStatus,
            'last_run_at' => $this->lastRunAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
