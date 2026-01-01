<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

final readonly class TtmFinancialRecord
{
    public function __construct(
        public int $companyId,
        public DateTimeImmutable $asOfDate,
        public ?float $revenue,
        public ?float $grossProfit,
        public ?float $operatingIncome,
        public ?float $ebitda,
        public ?float $netIncome,
        public ?float $operatingCashFlow,
        public ?float $capex,
        public ?float $freeCashFlow,
        public ?DateTimeImmutable $latestQuarterEnd,
        public ?DateTimeImmutable $previousQuarterEnd,
        public ?DateTimeImmutable $twoQuartersAgoEnd,
        public ?DateTimeImmutable $oldestQuarterEnd,
        public string $currency,
        public DateTimeImmutable $calculatedAt,
    ) {
    }
}
