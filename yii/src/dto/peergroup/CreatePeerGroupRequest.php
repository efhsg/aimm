<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for creating a new peer group.
 */
final readonly class CreatePeerGroupRequest
{
    /**
     * @param string[] $initialTickers
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $sector,
        public string $actorUsername,
        public ?string $description = null,
        public ?int $policyId = null,
        public array $initialTickers = [],
        public bool $isActive = true,
    ) {
    }
}
