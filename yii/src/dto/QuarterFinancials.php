<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use DateTimeImmutable;

/**
 * Quarterly financial metrics for a single quarter.
 */
final readonly class QuarterFinancials
{
    public function __construct(
        public int $fiscalYear,
        public int $fiscalQuarter,
        public DateTimeImmutable $periodEnd,
        public ?DataPointMoney $revenue = null,
        public ?DataPointMoney $ebitda = null,
        public ?DataPointMoney $netIncome = null,
        public ?DataPointMoney $freeCashFlow = null,
        /** @var array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> */
        public array $additionalMetrics = [],
    ) {
    }

    /**
     * Get the quarter key (e.g., "2024Q3").
     */
    public function getQuarterKey(): string
    {
        return "{$this->fiscalYear}Q{$this->fiscalQuarter}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
            'fiscal_quarter' => $this->fiscalQuarter,
            'period_end' => $this->periodEnd->format('Y-m-d'),
            'revenue' => $this->revenue?->toArray(),
            'ebitda' => $this->ebitda?->toArray(),
            'net_income' => $this->netIncome?->toArray(),
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
