<?php

declare(strict_types=1);

namespace app\dto;

/**
 * A candidate source URL for data collection.
 */
final readonly class SourceCandidate
{
    public function __construct(
        public string $url,
        public string $adapterId,
        public int $priority,
        public string $domain,
    ) {
    }
}
