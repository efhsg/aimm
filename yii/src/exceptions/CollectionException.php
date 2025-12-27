<?php

declare(strict_types=1);

namespace app\exceptions;

use Exception;

/**
 * Base exception for all collection errors.
 */
class CollectionException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $ticker = null,
        public readonly ?string $datapointKey = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
