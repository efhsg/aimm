<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for updating an existing peer group.
 */
final readonly class UpdatePeerGroupRequest
{
    public function __construct(
        public int $id,
        public string $name,
        public string $actorUsername,
        public ?string $description = null,
        public ?int $policyId = null,
    ) {
    }
}
