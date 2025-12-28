<?php

declare(strict_types=1);

namespace app\dto\datapoints;

use app\enums\SourceLocatorType;

/**
 * Records the exact location where a datapoint was extracted from.
 */
final readonly class SourceLocator
{
    private const MAX_SNIPPET_LENGTH = 100;

    public function __construct(
        public SourceLocatorType $type,
        public string $selector,
        public string $snippet,
    ) {
    }

    public static function html(string $selector, string $snippet): self
    {
        return new self(SourceLocatorType::Html, $selector, self::truncateSnippet($snippet));
    }

    public static function json(string $jsonPath, string $snippet): self
    {
        return new self(SourceLocatorType::Json, $jsonPath, self::truncateSnippet($snippet));
    }

    public static function xpath(string $xpath, string $snippet): self
    {
        return new self(SourceLocatorType::Xpath, $xpath, self::truncateSnippet($snippet));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'selector' => $this->selector,
            'snippet' => $this->snippet,
        ];
    }

    private static function truncateSnippet(string $snippet): string
    {
        if (mb_strlen($snippet) <= self::MAX_SNIPPET_LENGTH) {
            return $snippet;
        }

        return mb_substr($snippet, 0, self::MAX_SNIPPET_LENGTH - 3) . '...';
    }
}
