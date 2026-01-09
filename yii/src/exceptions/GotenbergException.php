<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * GotenbergException captures PDF rendering failures with retry hints.
 */
final class GotenbergException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly bool $retryable = false,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBodySnippet = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
