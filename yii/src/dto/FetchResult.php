<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * Result of fetching content from a URL.
 */
final readonly class FetchResult
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public string $content,
        public string $contentType,
        public int $statusCode,
        public string $url,
        public string $finalUrl,
        public DateTimeImmutable $retrievedAt,
        public array $headers = [],
    ) {
    }

    public function isHtml(): bool
    {
        return str_contains($this->contentType, 'text/html');
    }

    public function isJson(): bool
    {
        return str_contains($this->contentType, 'application/json');
    }

    public function wasRedirected(): bool
    {
        return $this->url !== $this->finalUrl;
    }
}
