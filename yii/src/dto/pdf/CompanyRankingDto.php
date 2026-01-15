<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Single company ranking row for PDF.
 */
final readonly class CompanyRankingDto
{
    public function __construct(
        public int $rank,
        public string $ticker,
        public string $name,
        public string $rating,
        public string $fundamentalsAssessment,
        public float $fundamentalsScore,
        public string $riskAssessment,
        public ?float $valuationGapPercent,
        public ?string $valuationGapDirection,
        public ?float $marketCapBillions,
    ) {
    }

    public function getRatingBadgeClass(): string
    {
        return match ($this->rating) {
            'buy' => 'badge--success',
            'sell' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getFundamentalsBadgeClass(): string
    {
        return match ($this->fundamentalsAssessment) {
            'improving' => 'badge--success',
            'deteriorating' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getRiskBadgeClass(): string
    {
        return match ($this->riskAssessment) {
            'acceptable' => 'badge--success',
            'unacceptable' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getGapClass(): string
    {
        return match ($this->valuationGapDirection) {
            'undervalued' => 'text-success',
            'overvalued' => 'text-danger',
            default => '',
        };
    }

    public function formatValuationGap(): string
    {
        if ($this->valuationGapPercent === null) {
            return '-';
        }
        $sign = $this->valuationGapPercent > 0 ? '+' : '';

        return $sign . number_format($this->valuationGapPercent, 1) . '%';
    }

    public function formatMarketCap(): string
    {
        if ($this->marketCapBillions === null) {
            return '-';
        }

        return '$' . number_format($this->marketCapBillions, 1) . 'B';
    }
}
