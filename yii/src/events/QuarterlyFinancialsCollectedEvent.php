<?php

declare(strict_types=1);

namespace app\events;

use DateTimeImmutable;

final readonly class QuarterlyFinancialsCollectedEvent
{
    public function __construct(
        public int $companyId,
        public DateTimeImmutable $periodEndDate,
    ) {
    }
}
