<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;

/**
 * Annual financial metrics for a single fiscal year.
 */
final readonly class AnnualFinancials
{
    public function __construct(
        public int $fiscalYear,
        public ?DataPointMoney $revenue = null,
        public ?DataPointMoney $ebitda = null,
        public ?DataPointMoney $netIncome = null,
        public ?DataPointMoney $netDebt = null,
        public ?DataPointMoney $freeCashFlow = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
            'revenue' => $this->revenue?->toArray(),
            'ebitda' => $this->ebitda?->toArray(),
            'net_income' => $this->netIncome?->toArray(),
            'net_debt' => $this->netDebt?->toArray(),
            'free_cash_flow' => $this->freeCashFlow?->toArray(),
        ];
    }
}
