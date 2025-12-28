<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;

/**
 * Valuation metrics for a company.
 */
final readonly class ValuationData
{
    public function __construct(
        public DataPointMoney $marketCap,
        public ?DataPointRatio $fwdPe = null,
        public ?DataPointRatio $trailingPe = null,
        public ?DataPointRatio $evEbitda = null,
        public ?DataPointMoney $freeCashFlowTtm = null,
        public ?DataPointPercent $fcfYield = null,
        public ?DataPointPercent $divYield = null,
        public ?DataPointRatio $netDebtEbitda = null,
        public ?DataPointRatio $priceToBook = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'market_cap' => $this->marketCap->toArray(),
            'fwd_pe' => $this->fwdPe?->toArray(),
            'trailing_pe' => $this->trailingPe?->toArray(),
            'ev_ebitda' => $this->evEbitda?->toArray(),
            'free_cash_flow_ttm' => $this->freeCashFlowTtm?->toArray(),
            'fcf_yield' => $this->fcfYield?->toArray(),
            'div_yield' => $this->divYield?->toArray(),
            'net_debt_ebitda' => $this->netDebtEbitda?->toArray(),
            'price_to_book' => $this->priceToBook?->toArray(),
        ];
    }
}
