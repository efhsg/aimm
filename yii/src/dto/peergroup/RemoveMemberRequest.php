<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for removing a member from a peer group.
 */
final readonly class RemoveMemberRequest
{
    public function __construct(
        public int $groupId,
        public int $companyId,
        public string $actorUsername,
    ) {
    }
}
