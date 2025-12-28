<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Complete collected data for a single company.
 */
final readonly class CompanyData
{
    public function __construct(
        public string $ticker,
        public string $name,
        public string $listingExchange,
        public string $listingCurrency,
        public string $reportingCurrency,
        public ValuationData $valuation,
        public FinancialsData $financials,
        public QuartersData $quarters,
        public ?OperationalData $operational = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'name' => $this->name,
            'listing_exchange' => $this->listingExchange,
            'listing_currency' => $this->listingCurrency,
            'reporting_currency' => $this->reportingCurrency,
            'valuation' => $this->valuation->toArray(),
            'financials' => $this->financials->toArray(),
            'quarters' => $this->quarters->toArray(),
            'operational' => $this->operational?->toArray(),
        ];
    }
}
