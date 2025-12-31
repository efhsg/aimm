<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

/**
 * Request DTO for creating a new industry config.
 */
final readonly class CreateIndustryConfigRequest
{
    public function __construct(
        public string $industryId,
        public string $configJson,
        public string $actorUsername,
        public bool $isActive = true,
    ) {
    }
}
