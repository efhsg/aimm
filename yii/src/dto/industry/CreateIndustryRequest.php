<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request DTO for creating a new industry.
 */
final readonly class CreateIndustryRequest
{
    public function __construct(
        public string $name,
        public string $slug,
        public int $sectorId,
        public string $actorUsername,
        public ?string $description = null,
        public ?int $policyId = null,
        public bool $isActive = true,
    ) {
    }
}
