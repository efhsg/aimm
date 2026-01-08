<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Current valuation metrics snapshot.
 */
final readonly class ValuationSnapshot
{
    public function __construct(
        public ?float $marketCapBillions,
        public ?float $fwdPe,
        public ?float $trailingPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
        public ?float $priceToBook,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'market_cap_billions' => $this->marketCapBillions !== null
                ? round($this->marketCapBillions, 2)
                : null,
            'fwd_pe' => $this->fwdPe !== null ? round($this->fwdPe, 2) : null,
            'trailing_pe' => $this->trailingPe !== null ? round($this->trailingPe, 2) : null,
            'ev_ebitda' => $this->evEbitda !== null ? round($this->evEbitda, 2) : null,
            'fcf_yield_percent' => $this->fcfYieldPercent !== null
                ? round($this->fcfYieldPercent, 2)
                : null,
            'div_yield_percent' => $this->divYieldPercent !== null
                ? round($this->divYieldPercent, 2)
                : null,
            'price_to_book' => $this->priceToBook !== null
                ? round($this->priceToBook, 2)
                : null,
        ];
    }
}
