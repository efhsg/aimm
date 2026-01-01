<?php

declare(strict_types=1);

namespace app\dto\peergroup;

/**
 * Response DTO for peer group list with counts.
 */
final readonly class PeerGroupListResponse
{
    /**
     * @param PeerGroupResponse[] $groups
     * @param array{total: int, active: int, inactive: int} $counts
     */
    public function __construct(
        public array $groups,
        public array $counts,
    ) {
    }
}
