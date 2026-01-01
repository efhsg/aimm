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
        public ?DataPointMoney $ebitda = null,
        public ?DataPointMoney $netIncome = null,
        public ?DataPointMoney $netDebt = null,
        public ?DataPointMoney $freeCashFlow = null,
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
            'ebitda' => $this->ebitda?->toArray(),
            'net_income' => $this->netIncome?->toArray(),
            'net_debt' => $this->netDebt?->toArray(),
            'free_cash_flow' => $this->freeCashFlow?->toArray(),
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
