<?php

declare(strict_types=1);

namespace app\exceptions;

use DateTimeImmutable;

/**
 * Rate limiting by source.
 */
class RateLimitException extends CollectionException
{
    public function __construct(
        string $message,
        public readonly string $domain,
        public readonly ?DateTimeImmutable $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
