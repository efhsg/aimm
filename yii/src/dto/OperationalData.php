<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;

/**
 * Industry-specific operational metrics for a company.
 *
 * This is an optional container for KPIs that vary by industry:
 * - Airlines: load factor, ASK, RPK
 * - Oil & Gas: production volumes, reserve replacement ratio
 * - Tech: DAU/MAU, ARPU, churn rate
 */
final readonly class OperationalData
{
    /**
     * @param array<string, DataPointMoney|DataPointNumber|DataPointPercent> $metrics
     */
    public function __construct(
        public array $metrics,
    ) {
    }

    /**
     * Get a specific operational metric by key.
     */
    public function getMetric(string $key): DataPointMoney|DataPointNumber|DataPointPercent|null
    {
        return $this->metrics[$key] ?? null;
    }

    /**
     * Check if a specific metric exists.
     */
    public function hasMetric(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (DataPointMoney|DataPointNumber|DataPointPercent $dp) => $dp->toArray(),
            $this->metrics
        );
    }
}
