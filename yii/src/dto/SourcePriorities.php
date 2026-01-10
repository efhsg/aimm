<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Per-industry source priority configuration.
 *
 * Defines the order in which adapters should be tried for each data type.
 * Empty arrays fall back to system defaults.
 */
final readonly class SourcePriorities
{
    /**
     * @param list<string> $valuation   Valuation metrics (P/E, Market Cap)
     * @param list<string> $financials  Annual financial statements
     * @param list<string> $quarters    Quarterly financial data
     * @param list<string> $macro       Economic indicators, FX rates
     * @param list<string> $benchmarks  Price history for indices/commodities
     */
    public function __construct(
        public array $valuation = [],
        public array $financials = [],
        public array $quarters = [],
        public array $macro = [],
        public array $benchmarks = [],
    ) {
    }

    public static function fromJson(?string $json): ?self
    {
        if ($json === null || $json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return new self(
            valuation: $data['valuation'] ?? [],
            financials: $data['financials'] ?? [],
            quarters: $data['quarters'] ?? [],
            macro: $data['macro'] ?? [],
            benchmarks: $data['benchmarks'] ?? [],
        );
    }

    /**
     * Get priority list for a specific data type.
     *
     * @return list<string>
     */
    public function getForDataType(string $type): array
    {
        return match ($type) {
            'valuation' => $this->valuation,
            'financials' => $this->financials,
            'quarters' => $this->quarters,
            'macro' => $this->macro,
            'benchmarks' => $this->benchmarks,
            default => [],
        };
    }

    /**
     * Check if priorities are defined for a data type.
     */
    public function hasForDataType(string $type): bool
    {
        return $this->getForDataType($type) !== [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function toArray(): array
    {
        return [
            'valuation' => $this->valuation,
            'financials' => $this->financials,
            'quarters' => $this->quarters,
            'macro' => $this->macro,
            'benchmarks' => $this->benchmarks,
        ];
    }
}
