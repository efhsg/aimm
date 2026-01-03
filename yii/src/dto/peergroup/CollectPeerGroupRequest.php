<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request to collect data for a peer group.
 */
final readonly class CollectPeerGroupRequest
{
    /**
     * @param list<string> $additionalFocals Additional focal tickers to collect (merged with is_focal members)
     */
    public function __construct(
        public int $groupId,
        public string $actorUsername,
        public array $additionalFocals = [],
        public int $batchSize = 10,
        public bool $enableMemoryManagement = true,
    ) {
    }
}
