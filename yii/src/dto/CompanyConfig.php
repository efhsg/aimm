<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for a single company in an industry.
 */
final readonly class CompanyConfig
{
    /**
     * @param list<string>|null $alternativeTickers
     */
    public function __construct(
        public string $ticker,
        public string $name,
        public string $listingExchange,
        public string $listingCurrency,
        public string $reportingCurrency,
        public int $fyEndMonth,
        public ?array $alternativeTickers = null,
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
            'fy_end_month' => $this->fyEndMonth,
            'alternative_tickers' => $this->alternativeTickers,
        ];
    }
}
