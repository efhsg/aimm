<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Historical financial data for a company.
 */
final readonly class FinancialsData
{
    /**
     * @param array<int, AnnualFinancials> $annualData Indexed by fiscal year
     */
    public function __construct(
        public int $historyYears,
        public array $annualData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'history_years' => $this->historyYears,
            'annual_data' => array_values(array_map(
                static fn (AnnualFinancials $annual) => $annual->toArray(),
                $this->annualData
            )),
        ];
    }
}
