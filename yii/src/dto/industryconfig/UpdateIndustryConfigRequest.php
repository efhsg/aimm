<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

/**
 * Request DTO for updating an existing industry config.
 */
final readonly class UpdateIndustryConfigRequest
{
    public function __construct(
        public string $industryId,
        public string $configJson,
        public string $actorUsername,
    ) {
    }
}
