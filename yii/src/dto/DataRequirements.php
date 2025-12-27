<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for data collection requirements.
 */
final readonly class DataRequirements
{
    /**
     * @param list<string> $requiredValuationMetrics
     * @param list<string> $optionalValuationMetrics
     */
    public function __construct(
        public int $historyYears,
        public int $quartersToFetch,
        public array $requiredValuationMetrics,
        public array $optionalValuationMetrics,
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
            'required_valuation_metrics' => $this->requiredValuationMetrics,
            'optional_valuation_metrics' => $this->optionalValuationMetrics,
        ];
    }
}
