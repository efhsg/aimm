<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Average valuation metrics across peer companies (excluding focal).
 */
final readonly class PeerAverages
{
    public function __construct(
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
        public ?float $marketCapBillions,
        public int $companiesIncluded,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fwd_pe' => $this->fwdPe !== null ? round($this->fwdPe, 2) : null,
            'ev_ebitda' => $this->evEbitda !== null ? round($this->evEbitda, 2) : null,
            'fcf_yield_percent' => $this->fcfYieldPercent !== null ? round($this->fcfYieldPercent, 2) : null,
            'div_yield_percent' => $this->divYieldPercent !== null ? round($this->divYieldPercent, 2) : null,
            'market_cap_billions' => $this->marketCapBillions !== null ? round($this->marketCapBillions, 2) : null,
            'companies_included' => $this->companiesIncluded,
        ];
    }
}
