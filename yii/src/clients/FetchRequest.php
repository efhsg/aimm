<?php

declare(strict_types=1);

namespace app\clients;

/**
 * Request parameters for fetching web content.
 */
final readonly class FetchRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $url,
        public array $headers = [],
        public int $timeoutSeconds = 30,
        public bool $followRedirects = true,
        public ?string $userAgent = null,
    ) {
    }
}
