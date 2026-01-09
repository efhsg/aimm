<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Rendered HTML views for PDF generation.
 *
 * Contains the HTML strings produced by the ViewRenderer.
 */
final readonly class RenderedViews
{
    public function __construct(
        public string $indexHtml,
        public string $headerHtml,
        public string $footerHtml,
    ) {
    }
}
