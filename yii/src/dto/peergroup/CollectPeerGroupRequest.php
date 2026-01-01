<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request to collect data for a peer group.
 */
final readonly class CollectPeerGroupRequest
{
    public function __construct(
        public int $groupId,
        public string $actorUsername,
        public ?string $focalTickerOverride = null,
        public int $batchSize = 10,
        public bool $enableMemoryManagement = true,
    ) {
    }
}
