<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for setting a focal company in a peer group.
 */
final readonly class SetFocalRequest
{
    public function __construct(
        public int $groupId,
        public int $companyId,
        public string $actorUsername,
    ) {
    }
}
