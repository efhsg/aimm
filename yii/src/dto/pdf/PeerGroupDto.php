<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Peer group information for PDF reports.
 */
final readonly class PeerGroupDto
{
    /**
     * @param string[] $companies List of peer company names
     */
    public function __construct(
        public string $name,
        public array $companies,
    ) {
    }
}
