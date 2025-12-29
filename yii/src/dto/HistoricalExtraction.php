<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\SourceLocator;

/**
 * Extraction containing historical period data (e.g., annual financials, quarterly data).
 *
 * Unlike Extraction which holds a single scalar value, this holds an array of
 * periods with their respective values and dates.
 */
final readonly class HistoricalExtraction
{
    /**
     * @param list<PeriodValue> $periods
     */
    public function __construct(
        public string $datapointKey,
        public array $periods,
        public string $unit,
        public ?string $currency = null,
        public ?string $scale = null,
        public SourceLocator $locator,
    ) {
    }

    public function isEmpty(): bool
    {
        return count($this->periods) === 0;
    }

    public function getPeriodCount(): int
    {
        return count($this->periods);
    }

    /**
     * Get periods grouped by fiscal year.
     *
     * @return array<int, list<PeriodValue>>
     */
    public function getByYear(): array
    {
        $byYear = [];
        foreach ($this->periods as $period) {
            $year = (int) $period->endDate->format('Y');
            $byYear[$year][] = $period;
        }
        krsort($byYear);
        return $byYear;
    }

    /**
     * Get periods grouped by quarter key (e.g., "2024Q3").
     *
     * @return array<string, PeriodValue>
     */
    public function getByQuarter(): array
    {
        $byQuarter = [];
        foreach ($this->periods as $period) {
            $year = (int) $period->endDate->format('Y');
            $month = (int) $period->endDate->format('n');
            $quarter = (int) ceil($month / 3);
            $key = "{$year}Q{$quarter}";
            $byQuarter[$key] = $period;
        }
        krsort($byQuarter);
        return $byQuarter;
    }
}
