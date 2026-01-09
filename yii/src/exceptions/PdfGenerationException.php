<?php

declare(strict_types=1);

namespace app\exceptions;

use RuntimeException;

/**
 * Exception for PDF generation failures.
 */
final class PdfGenerationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly bool $retryable = false,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function reportNotFound(string $reportId): self
    {
        return new self(
            "Report not found: {$reportId}",
            'REPORT_NOT_FOUND',
            retryable: false,
        );
    }

    public static function renderFailed(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            "Render failed: {$message}",
            'RENDER_FAILED',
            retryable: true,
            previous: $previous,
        );
    }

    public static function storageFailed(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            "Storage failed: {$message}",
            'STORAGE_FAILED',
            retryable: true,
            previous: $previous,
        );
    }
}
