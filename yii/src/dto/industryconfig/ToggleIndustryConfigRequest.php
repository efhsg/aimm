<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

/**
 * Request DTO for toggling industry config active status.
 */
final readonly class ToggleIndustryConfigRequest
{
    public function __construct(
        public string $industryId,
        public string $actorUsername,
    ) {
    }
}
