<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request DTO for updating an existing industry.
 */
final readonly class UpdateIndustryRequest
{
    public function __construct(
        public int $id,
        public string $name,
        public string $actorUsername,
        public ?string $description = null,
        public ?int $policyId = null,
    ) {
    }
}
