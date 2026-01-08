<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Summary of a single peer company for comparison.
 */
final readonly class PeerSummary
{
    public function __construct(
        public string $ticker,
        public string $name,
        public ?float $marketCapBillions,
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'name' => $this->name,
            'market_cap_billions' => $this->marketCapBillions !== null
                ? round($this->marketCapBillions, 2)
                : null,
            'fwd_pe' => $this->fwdPe !== null ? round($this->fwdPe, 2) : null,
            'ev_ebitda' => $this->evEbitda !== null ? round($this->evEbitda, 2) : null,
            'fcf_yield_percent' => $this->fcfYieldPercent !== null
                ? round($this->fcfYieldPercent, 2)
                : null,
            'div_yield_percent' => $this->divYieldPercent !== null
                ? round($this->divYieldPercent, 2)
                : null,
        ];
    }
}
