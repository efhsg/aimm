<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Quarterly financial data for a company.
 */
final readonly class QuartersData
{
    /**
     * @param array<string, QuarterFinancials> $quarters Indexed by quarter key (e.g., "2024Q3")
     */
    public function __construct(
        public array $quarters,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $quarters = [];
        foreach ($this->quarters as $key => $quarter) {
            $quarters[$key] = $quarter->toArray();
        }

        if ($quarters === []) {
            $quarters = new \stdClass();
        }

        return [
            'quarters' => $quarters,
        ];
    }
}
