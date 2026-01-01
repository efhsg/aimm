<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

final readonly class QuarterlyFinancialRecord
{
    public function __construct(
        public int $companyId,
        public int $fiscalYear,
        public int $fiscalQuarter,
        public DateTimeImmutable $periodEndDate,
        public ?float $revenue,
        public ?float $grossProfit,
        public ?float $operatingIncome,
        public ?float $ebitda,
        public ?float $netIncome,
        public ?float $eps,
        public ?float $operatingCashFlow,
        public ?float $capex,
        public ?float $freeCashFlow,
        public string $currency,
        public string $sourceAdapter,
        public ?string $sourceUrl,
        public DateTimeImmutable $collectedAt,
        public int $version = 1,
        public bool $isCurrent = true,
    ) {
    }
}
