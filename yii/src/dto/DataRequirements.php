<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for data collection requirements.
 */
final readonly class DataRequirements
{
    /**
     * @param list<MetricDefinition> $valuationMetrics
     * @param list<MetricDefinition> $annualFinancialMetrics
     * @param list<MetricDefinition> $quarterMetrics
     * @param list<MetricDefinition> $operationalMetrics
     */
    public function __construct(
        public int $historyYears,
        public int $quartersToFetch,
        public array $valuationMetrics,
        public array $annualFinancialMetrics,
        public array $quarterMetrics,
        public array $operationalMetrics,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'history_years' => $this->historyYears,
            'quarters_to_fetch' => $this->quartersToFetch,
            'valuation_metrics' => array_map(
                static fn (MetricDefinition $metric): array => $metric->toArray(),
                $this->valuationMetrics
            ),
            'annual_financial_metrics' => array_map(
                static fn (MetricDefinition $metric): array => $metric->toArray(),
                $this->annualFinancialMetrics
            ),
            'quarter_metrics' => array_map(
                static fn (MetricDefinition $metric): array => $metric->toArray(),
                $this->quarterMetrics
            ),
            'operational_metrics' => array_map(
                static fn (MetricDefinition $metric): array => $metric->toArray(),
                $this->operationalMetrics
            ),
        ];
    }
}
