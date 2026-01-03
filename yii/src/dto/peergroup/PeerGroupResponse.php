<?php

declare(strict_types=1);

namespace app\dto\peergroup;

use DateTimeImmutable;

/**
 * Response DTO for a single peer group with stats.
 */
final readonly class PeerGroupResponse
{
    /**
     * @param list<string> $focalTickers
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $sector,
        public ?string $description,
        public ?int $policyId,
        public ?string $policyName,
        public bool $isActive,
        public int $memberCount,
        public int $focalCount,
        public array $focalTickers,
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
            'sector' => $this->sector,
            'description' => $this->description,
            'policy_id' => $this->policyId,
            'policy_name' => $this->policyName,
            'is_active' => $this->isActive,
            'member_count' => $this->memberCount,
            'focal_count' => $this->focalCount,
            'focal_tickers' => $this->focalTickers,
            'last_run_status' => $this->lastRunStatus,
            'last_run_at' => $this->lastRunAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
