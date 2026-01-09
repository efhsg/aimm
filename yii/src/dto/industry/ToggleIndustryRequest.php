<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request DTO for toggling industry active status.
 */
final readonly class ToggleIndustryRequest
{
    public function __construct(
        public int $id,
        public bool $isActive,
        public string $actorUsername,
    ) {
    }
}
