<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * A single period's value in a historical data series.
 */
final readonly class PeriodValue
{
    public function __construct(
        public DateTimeImmutable $endDate,
        public float $value,
    ) {
    }

    public function getFiscalYear(): int
    {
        return (int) $this->endDate->format('Y');
    }

    public function getFiscalQuarter(): int
    {
        $month = (int) $this->endDate->format('n');
        return (int) ceil($month / 3);
    }

    public function getQuarterKey(): string
    {
        return "{$this->getFiscalYear()}Q{$this->getFiscalQuarter()}";
    }
}
