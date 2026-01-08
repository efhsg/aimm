<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Peer comparison section with averages and individual peers.
 */
final readonly class PeerComparison
{
    /**
     * @param PeerSummary[] $peers
     */
    public function __construct(
        public int $peerCount,
        public PeerAverages $averages,
        public array $peers,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'peer_count' => $this->peerCount,
            'averages' => $this->averages->toArray(),
            'peers' => array_map(
                static fn (PeerSummary $p): array => $p->toArray(),
                $this->peers
            ),
        ];
    }
}
