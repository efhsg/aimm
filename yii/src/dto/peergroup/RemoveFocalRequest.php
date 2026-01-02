<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request to remove focal designation from a company in a peer group.
 */
final readonly class RemoveFocalRequest
{
    public function __construct(
        public int $groupId,
        public int $companyId,
        public ?string $actorUsername = null,
    ) {
    }
}
