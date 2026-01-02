<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request to clear all focal designations from a peer group.
 */
final readonly class ClearFocalsRequest
{
    public function __construct(
        public int $groupId,
        public ?string $actorUsername = null,
    ) {
    }
}
