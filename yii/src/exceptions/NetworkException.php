<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * Network-level failures (connection, timeout).
 */
class NetworkException extends CollectionException
{
    public function __construct(
        string $message,
        public readonly string $url,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, null, null, $previous);
    }
}
