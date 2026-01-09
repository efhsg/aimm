<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Chart information for PDF reports.
 *
 * In Phase 3, charts are not yet generated (deferred to Phase 4).
 * This DTO includes placeholder support for unavailable charts.
 */
final readonly class ChartDto
{
    public function __construct(
        public string $id,
        public string $type,
        public bool $available = false,
        public ?string $pngBytes = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $placeholderMessage = null,
    ) {
    }

    /**
     * Create a placeholder chart (not yet available).
     */
    public static function placeholder(string $id, string $type, string $message = 'Chart not available'): self
    {
        return new self(
            id: $id,
            type: $type,
            available: false,
            placeholderMessage: $message,
        );
    }

    /**
     * Create an available chart with PNG data.
     */
    public static function withImage(
        string $id,
        string $type,
        string $pngBytes,
        int $width,
        int $height,
    ): self {
        return new self(
            id: $id,
            type: $type,
            available: true,
            pngBytes: $pngBytes,
            width: $width,
            height: $height,
        );
    }
}
