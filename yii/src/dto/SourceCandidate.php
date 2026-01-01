<?php

declare(strict_types=1);

namespace app\dto;

/**
 * A candidate source URL for data collection.
 */
final readonly class SourceCandidate
{
    /**
     * @param array<string, string> $headers Optional headers for authenticated APIs
     */
    public function __construct(
        public string $url,
        public string $adapterId,
        public int $priority,
        public string $domain,
        public array $headers = [],
    ) {
    }
}
