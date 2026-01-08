<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Single row of annual financial data for display.
 */
final readonly class AnnualFinancialRow
{
    public function __construct(
        public int $fiscalYear,
        public ?float $revenueBillions,
        public ?float $ebitdaBillions,
        public ?float $netIncomeBillions,
        public ?float $fcfBillions,
        public ?float $ebitdaMarginPercent,
        public ?float $netDebtBillions,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
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
            'ebitda_margin_percent' => $this->ebitdaMarginPercent !== null
                ? round($this->ebitdaMarginPercent, 2)
                : null,
            'net_debt_billions' => $this->netDebtBillions !== null
                ? round($this->netDebtBillions, 2)
                : null,
        ];
    }
}
