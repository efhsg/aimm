<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

use DateTimeImmutable;

/**
 * Response DTO for a single industry config.
 */
final readonly class IndustryConfigResponse
{
    public function __construct(
        public int $id,
        public string $industryId,
        public string $name,
        public string $configJson,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $createdBy,
        public ?string $updatedBy,
        public bool $isJsonValid = true,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'industry_id' => $this->industryId,
            'name' => $this->name,
            'config_json' => $this->configJson,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'is_json_valid' => $this->isJsonValid,
        ];
    }
}
