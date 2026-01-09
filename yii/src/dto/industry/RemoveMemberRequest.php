<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request DTO for removing a member from an industry.
 */
final readonly class RemoveMemberRequest
{
    public function __construct(
        public int $industryId,
        public int $companyId,
        public string $actorUsername,
    ) {
    }
}
