<?php

declare(strict_types=1);

namespace app\exceptions;

use Exception;

/**
 * Gate validation failures.
 */
class GateFailedException extends Exception
{
    /**
     * @param array<int, mixed> $errors GateError[]
     */
    public function __construct(
        string $message,
        public readonly array $errors,
    ) {
        parent::__construct($message);
    }
}
