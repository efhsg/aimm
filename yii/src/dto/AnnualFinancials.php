<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;

/**
 * Annual financial metrics for a single fiscal year.
 */
final readonly class AnnualFinancials
{
    public function __construct(
        public int $fiscalYear,
        public ?\DateTimeImmutable $periodEndDate = null,
        public ?DataPointMoney $revenue = null,
        public ?DataPointMoney $grossProfit = null,
        public ?DataPointMoney $operatingIncome = null,
        public ?DataPointMoney $ebitda = null,
        public ?DataPointMoney $netIncome = null,
        public ?DataPointMoney $freeCashFlow = null,
        public ?DataPointMoney $totalAssets = null,
        public ?DataPointMoney $totalLiabilities = null,
        public ?DataPointMoney $totalEquity = null,
        public ?DataPointMoney $totalDebt = null,
        public ?DataPointMoney $cashAndEquivalents = null,
        public ?DataPointMoney $netDebt = null,
        public ?DataPointNumber $sharesOutstanding = null,
        /** @var array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> */
        public array $additionalMetrics = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
            'period_end_date' => $this->periodEndDate?->format('Y-m-d'),
            'revenue' => $this->revenue?->toArray(),
            'gross_profit' => $this->grossProfit?->toArray(),
            'operating_income' => $this->operatingIncome?->toArray(),
            'ebitda' => $this->ebitda?->toArray(),
            'net_income' => $this->netIncome?->toArray(),
            'free_cash_flow' => $this->freeCashFlow?->toArray(),
            'total_assets' => $this->totalAssets?->toArray(),
            'total_liabilities' => $this->totalLiabilities?->toArray(),
            'total_equity' => $this->totalEquity?->toArray(),
            'total_debt' => $this->totalDebt?->toArray(),
            'cash_and_equivalents' => $this->cashAndEquivalents?->toArray(),
            'net_debt' => $this->netDebt?->toArray(),
            'shares_outstanding' => $this->sharesOutstanding?->toArray(),
            'additional_metrics' => $this->mapAdditionalMetrics(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function mapAdditionalMetrics(): ?array
    {
        if ($this->additionalMetrics === []) {
            return null;
        }

        $mapped = [];
        foreach ($this->additionalMetrics as $key => $datapoint) {
            $mapped[$key] = $datapoint->toArray();
        }

        return $mapped;
    }
}
