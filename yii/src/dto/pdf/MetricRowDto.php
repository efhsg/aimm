<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * A single metric row for financial tables in PDF reports.
 */
final readonly class MetricRowDto
{
    public const FORMAT_NUMBER = 'number';
    public const FORMAT_CURRENCY = 'currency';
    public const FORMAT_PERCENT = 'percent';

    public function __construct(
        public string $label,
        public ?float $value,
        public ?float $change,
        public ?float $peerAverage,
        public string $format = self::FORMAT_NUMBER,
    ) {
    }

    /**
     * Format the main value for display.
     */
    public function formatValue(): string
    {
        return $this->formatNumber($this->value);
    }

    /**
     * Format the year-over-year change for display.
     */
    public function formatChange(): string
    {
        if ($this->change === null) {
            return '-';
        }

        $sign = $this->change >= 0 ? '+' : '';

        return $sign . number_format($this->change * 100, 1) . '%';
    }

    /**
     * Format the peer average for display.
     */
    public function formatPeerAverage(): string
    {
        return $this->formatNumber($this->peerAverage);
    }

    /**
     * Format a number based on the metric's format type.
     */
    private function formatNumber(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return match ($this->format) {
            self::FORMAT_CURRENCY => $this->formatCurrency($value),
            self::FORMAT_PERCENT => number_format($value * 100, 1) . '%',
            default => number_format($value, 2),
        };
    }

    /**
     * Format currency with appropriate scale (M/B).
     */
    private function formatCurrency(float $value): string
    {
        $absValue = abs($value);

        if ($absValue >= 1_000_000_000) {
            return '$' . number_format($value / 1_000_000_000, 1) . 'B';
        }

        if ($absValue >= 1_000_000) {
            return '$' . number_format($value / 1_000_000, 1) . 'M';
        }

        if ($absValue >= 1_000) {
            return '$' . number_format($value / 1_000, 1) . 'K';
        }

        return '$' . number_format($value, 0);
    }
}
