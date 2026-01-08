<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Summary of annual and quarterly financial data.
 */
final readonly class FinancialsSummary
{
    /**
     * @param AnnualFinancialRow[] $annualData
     * @param QuarterlyFinancialRow[] $quarterlyData
     */
    public function __construct(
        public array $annualData,
        public array $quarterlyData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'annual_data' => array_map(
                static fn (AnnualFinancialRow $r): array => $r->toArray(),
                $this->annualData
            ),
            'quarterly_data' => array_map(
                static fn (QuarterlyFinancialRow $r): array => $r->toArray(),
                $this->quarterlyData
            ),
        ];
    }
}
