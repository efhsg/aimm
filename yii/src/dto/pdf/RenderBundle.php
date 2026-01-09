<?php

declare(strict_types=1);

namespace app\dto\pdf;

use app\factories\pdf\RenderBundleFactory;

/**
 * RenderBundle represents a complete set of files required to render a PDF.
 *
 * It contains the main HTML, optional header/footer, and any auxiliary assets (CSS, images).
 */
final readonly class RenderBundle
{
    /**
     * @param array<string, string|resource> $files relative path => bytes OR stream
     */
    public function __construct(
        public string $traceId,
        public string $indexHtml,
        public ?string $headerHtml,
        public ?string $footerHtml,
        public array $files,
        public int $totalBytes,
    ) {
    }

    public static function factory(string $traceId): RenderBundleFactory
    {
        return new RenderBundleFactory($traceId);
    }
}
