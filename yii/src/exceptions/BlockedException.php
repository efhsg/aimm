<?php

declare(strict_types=1);

namespace app\exceptions;

use DateTimeImmutable;

/**
 * Blocked by source (e.g., 401/403, bot challenge).
 */
class BlockedException extends CollectionException
{
    public function __construct(
        string $message,
        public readonly string $domain,
        public readonly string $url,
        public readonly ?DateTimeImmutable $retryAfter = null,
    ) {
        parent::__construct($message);
    }
}
