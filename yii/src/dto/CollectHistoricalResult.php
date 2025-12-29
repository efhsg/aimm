<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;

/**
 * Output DTO for historical datapoint collection.
 *
 * Contains period-based data indexed by fiscal year or quarter key.
 */
final readonly class CollectHistoricalResult
{
    /**
     * @param array<int|string, DataPointMoney> $periodDatapoints Keyed by year (int) or quarter key (string)
     * @param SourceAttempt[] $sourceAttempts All fetch attempts made
     */
    public function __construct(
        public string $datapointKey,
        public array $periodDatapoints,
        public array $sourceAttempts,
        public bool $found,
        public string $unit,
        public ?string $currency = null,
    ) {
    }

    public function getPeriodCount(): int
    {
        return count($this->periodDatapoints);
    }

    /**
     * @return list<int>
     */
    public function getYears(): array
    {
        $years = [];
        foreach (array_keys($this->periodDatapoints) as $key) {
            if (is_int($key)) {
                $years[] = $key;
            }
        }
        rsort($years);
        return $years;
    }

    public function getForYear(int $year): ?DataPointMoney
    {
        return $this->periodDatapoints[$year] ?? null;
    }

    public function getForQuarter(string $quarterKey): ?DataPointMoney
    {
        return $this->periodDatapoints[$quarterKey] ?? null;
    }
}
