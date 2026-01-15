<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Industry group averages for ranking PDF.
 */
final readonly class GroupAveragesDto
{
    public function __construct(
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
    ) {
    }

    public function formatFwdPe(): string
    {
        return $this->fwdPe !== null ? number_format($this->fwdPe, 1) . 'x' : '-';
    }

    public function formatEvEbitda(): string
    {
        return $this->evEbitda !== null ? number_format($this->evEbitda, 1) . 'x' : '-';
    }

    public function formatFcfYield(): string
    {
        return $this->fcfYieldPercent !== null ? number_format($this->fcfYieldPercent, 1) . '%' : '-';
    }

    public function formatDivYield(): string
    {
        return $this->divYieldPercent !== null ? number_format($this->divYieldPercent, 2) . '%' : '-';
    }
}
