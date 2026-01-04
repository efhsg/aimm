<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * Request to collect historical stock price data for a ticker.
 */
final readonly class CollectPriceHistoryRequest
{
    public function __construct(
        public string $ticker,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public string $currency = 'USD',
        public ?string $exchange = null,
    ) {
    }
}
