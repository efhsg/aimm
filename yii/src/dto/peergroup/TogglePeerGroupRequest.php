<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for toggling peer group active status.
 */
final readonly class TogglePeerGroupRequest
{
    public function __construct(
        public int $id,
        public bool $isActive,
        public string $actorUsername,
    ) {
    }
}
