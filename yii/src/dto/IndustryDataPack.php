<?php

declare(strict_types=1);

namespace app\dto;

use DateTimeImmutable;

/**
 * The primary output of Phase 1: Collection.
 *
 * Contains all collected data for an industry including:
 * - Macro-level indicators
 * - Per-company valuation, financials, and operational data
 * - Collection log with timing and status information
 */
final readonly class IndustryDataPack
{
    /**
     * @param array<string, CompanyData> $companies Indexed by ticker
     */
    public function __construct(
        public string $industryId,
        public string $datapackId,
        public DateTimeImmutable $collectedAt,
        public MacroData $macro,
        public array $companies,
        public CollectionLog $collectionLog,
    ) {
    }

    /**
     * Get a specific company's data by ticker.
     */
    public function getCompany(string $ticker): ?CompanyData
    {
        return $this->companies[$ticker] ?? null;
    }

    /**
     * Check if a company exists in the datapack.
     */
    public function hasCompany(string $ticker): bool
    {
        return isset($this->companies[$ticker]);
    }

    /**
     * Get all company tickers.
     *
     * @return list<string>
     */
    public function getTickers(): array
    {
        return array_keys($this->companies);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'industry_id' => $this->industryId,
            'datapack_id' => $this->datapackId,
            'collected_at' => $this->collectedAt->format(DateTimeImmutable::ATOM),
            'macro' => $this->macro->toArray(),
            'companies' => array_map(
                static fn (CompanyData $c) => $c->toArray(),
                $this->companies
            ),
            'collection_log' => $this->collectionLog->toArray(),
        ];
    }
}
