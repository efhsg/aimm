<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request to add a company as a focal in a peer group.
 */
final readonly class AddFocalRequest
{
    public function __construct(
        public int $groupId,
        public int $companyId,
        public ?string $actorUsername = null,
    ) {
    }
}
