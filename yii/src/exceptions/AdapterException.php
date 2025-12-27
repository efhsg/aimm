<?php

declare(strict_types=1);

namespace app\exceptions;

/**
 * Adapter parse failures.
 */
class AdapterException extends CollectionException
{
    public function __construct(
        string $message,
        public readonly string $adapterId,
        public readonly ?string $url = null,
    ) {
        parent::__construct($message);
    }
}
