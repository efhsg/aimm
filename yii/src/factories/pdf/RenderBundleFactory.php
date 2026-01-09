<?php

declare(strict_types=1);

namespace app\factories\pdf;

use app\dto\pdf\RenderBundle;
use app\exceptions\BundleSizeExceededException;
use app\exceptions\RenderBundleValidationException;
use app\exceptions\SecurityException;
use Yii;

/**
 * RenderBundleFactory provides a fluent interface for creating a RenderBundle.
 *
 * It enforces security constraints (no external resources, no path traversal) and size limits.
 */
final class RenderBundleFactory
{
    private string $indexHtml = '';
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;

    /** @var array<string, string|resource> */
    private array $files = [];
    private int $totalBytes = 0;

    // Size thresholds are fixed to prevent large in-memory payloads.
    private const SIZE_WARN_BYTES = 10 * 1024 * 1024;
    private const SIZE_FAIL_BYTES = 50 * 1024 * 1024;

    private const CSS_EXTENSIONS = ['css', 'scss'];

    public function __construct(
        private readonly string $traceId,
    ) {
    }

    /**
     * @throws SecurityException
     */
    public function withIndexHtml(string $html): self
    {
        $this->assertNoExternalRefs($html, 'index.html');
        $this->indexHtml = $html;
        return $this;
    }

    /**
     * @throws SecurityException
     */
    public function withHeaderHtml(?string $html): self
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html, 'header.html');
        }
        $this->headerHtml = $html;
        return $this;
    }

    /**
     * @throws SecurityException
     */
    public function withFooterHtml(?string $html): self
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html, 'footer.html');
        }
        $this->footerHtml = $html;
        return $this;
    }

    /**
     * @param string|resource $content
     *
     * @throws SecurityException
     */
    public function addFile(string $path, $content, ?int $byteSize = null): self
    {
        $this->validatePath($path);

        if ($this->isTextAsset($path) && is_string($content)) {
            $this->assertNoExternalRefs($content, $path);
        }

        $this->files[$path] = $content;

        if ($byteSize !== null) {
            $this->totalBytes += $byteSize;
        }

        return $this;
    }

    /**
     * @throws BundleSizeExceededException
     * @throws RenderBundleValidationException
     */
    public function build(): RenderBundle
    {
        if ($this->indexHtml === '') {
            throw new RenderBundleValidationException('indexHtml is required');
        }

        if ($this->totalBytes > self::SIZE_FAIL_BYTES) {
            throw new BundleSizeExceededException(
                "Bundle size {$this->totalBytes} exceeds limit " . self::SIZE_FAIL_BYTES
            );
        }

        if ($this->totalBytes > self::SIZE_WARN_BYTES) {
            Yii::warning("RenderBundle size {$this->totalBytes} exceeds warning threshold");
        }

        return new RenderBundle(
            $this->traceId,
            $this->indexHtml,
            $this->headerHtml,
            $this->footerHtml,
            $this->files,
            $this->totalBytes,
        );
    }

    private function isTextAsset(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::CSS_EXTENSIONS, true);
    }

    /**
     * @throws SecurityException
     */
    private function validatePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/')) {
            throw new SecurityException('Absolute paths forbidden');
        }
        if (str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new SecurityException('Path traversal forbidden');
        }
        if (!preg_match('#^[a-zA-Z0-9._/-]+$#', $path)) {
            throw new SecurityException('Invalid path characters');
        }
    }

    /**
     * @throws SecurityException
     */
    private function assertNoExternalRefs(string $text, string $source): void
    {
        $patterns = [
            '#(src|href|srcset)\s*=\s*["\']\s*(https?:|//)#i',
            '#url\s*\(\s*["\']?\s*(https?:|//)#i',
            '#@import\s+["\']\s*(https?:|//)#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                throw new SecurityException(
                    "External resources forbidden in RenderBundle: {$source}"
                );
            }
        }
    }
}
