<?php

declare(strict_types=1);

namespace app\transformers;

use app\dto\CompanyData;
use app\dto\report\PeerAverages;

/**
 * Calculates average valuation metrics across all companies in a group.
 */
final class PeerAverageTransformer
{
    private const BILLIONS = 1_000_000_000;

    /**
     * Calculate average valuations across all companies.
     *
     * @param array<string, CompanyData> $companies Indexed by ticker
     */
    public function transform(array $companies): PeerAverages
    {
        if ($companies === []) {
            return new PeerAverages(
                fwdPe: null,
                evEbitda: null,
                fcfYieldPercent: null,
                divYieldPercent: null,
                marketCapBillions: null,
                companiesIncluded: 0,
            );
        }

        return new PeerAverages(
            fwdPe: $this->average($companies, static fn (CompanyData $c): ?float => $c->valuation->fwdPe?->value),
            evEbitda: $this->average($companies, static fn (CompanyData $c): ?float => $c->valuation->evEbitda?->value),
            fcfYieldPercent: $this->average($companies, static fn (CompanyData $c): ?float => $c->valuation->fcfYield?->value),
            divYieldPercent: $this->average($companies, static fn (CompanyData $c): ?float => $c->valuation->divYield?->value),
            marketCapBillions: $this->average(
                $companies,
                fn (CompanyData $c): ?float => $this->toMarketCapBillions($c)
            ),
            companiesIncluded: count($companies),
        );
    }

    /**
     * Calculate average of non-null values.
     *
     * @param array<string, CompanyData> $companies
     * @param callable(CompanyData): ?float $extractor
     */
    private function average(array $companies, callable $extractor): ?float
    {
        $values = array_filter(
            array_map($extractor, $companies),
            static fn (?float $v): bool => $v !== null
        );

        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private function toMarketCapBillions(CompanyData $company): ?float
    {
        $baseValue = $company->valuation->marketCap->getBaseValue();

        if ($baseValue === null) {
            return null;
        }

        return $baseValue / self::BILLIONS;
    }
}
