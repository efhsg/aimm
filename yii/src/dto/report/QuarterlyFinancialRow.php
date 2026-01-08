<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Single row of quarterly financial data for display.
 */
final readonly class QuarterlyFinancialRow
{
    public function __construct(
        public string $quarterKey,
        public int $fiscalYear,
        public int $fiscalQuarter,
        public ?float $revenueBillions,
        public ?float $ebitdaBillions,
        public ?float $netIncomeBillions,
        public ?float $fcfBillions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'quarter_key' => $this->quarterKey,
            'fiscal_year' => $this->fiscalYear,
            'fiscal_quarter' => $this->fiscalQuarter,
            'revenue_billions' => $this->revenueBillions !== null
                ? round($this->revenueBillions, 2)
                : null,
            'ebitda_billions' => $this->ebitdaBillions !== null
                ? round($this->ebitdaBillions, 2)
                : null,
            'net_income_billions' => $this->netIncomeBillions !== null
                ? round($this->netIncomeBillions, 2)
                : null,
            'fcf_billions' => $this->fcfBillions !== null
                ? round($this->fcfBillions, 2)
                : null,
        ];
    }
}
