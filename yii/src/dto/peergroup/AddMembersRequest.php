<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Request DTO for adding members to a peer group.
 */
final readonly class AddMembersRequest
{
    /**
     * @param string[] $tickers
     */
    public function __construct(
        public int $groupId,
        public array $tickers,
        public string $actorUsername,
    ) {
    }
}
