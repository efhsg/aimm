<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

final readonly class AnnualFinancialRecord
{
    public function __construct(
        public int $companyId,
        public int $fiscalYear,
        public DateTimeImmutable $periodEndDate,
        public ?float $revenue,
        public ?float $costOfRevenue,
        public ?float $grossProfit,
        public ?float $operatingIncome,
        public ?float $ebitda,
        public ?float $netIncome,
        public ?float $eps,
        public ?float $operatingCashFlow,
        public ?float $capex,
        public ?float $freeCashFlow,
        public ?float $dividendsPaid,
        public ?float $totalAssets,
        public ?float $totalLiabilities,
        public ?float $totalEquity,
        public ?float $totalDebt,
        public ?float $cashAndEquivalents,
        public ?float $netDebt,
        public ?int $sharesOutstanding,
        public string $currency,
        public string $sourceAdapter,
        public ?string $sourceUrl,
        public DateTimeImmutable $collectedAt,
        public int $version = 1,
        public bool $isCurrent = true,
    ) {
    }
}
