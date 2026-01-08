<?php

declare(strict_types=1);

namespace app\transformers;

use app\dto\CompanyData;
use app\dto\report\PeerAverages;

/**
 * Calculates average valuation metrics across peer companies.
 *
 * Always excludes the focal company from averages.
 */
final class PeerAverageTransformer
{
    private const BILLIONS = 1_000_000_000;

    /**
     * Calculate average valuations across peers (excluding focal).
     *
     * @param array<string, CompanyData> $companies Indexed by ticker
     */
    public function transform(array $companies, string $focalTicker): PeerAverages
    {
        $peers = array_filter(
            $companies,
            static fn (CompanyData $c): bool => $c->ticker !== $focalTicker
        );

        if ($peers === []) {
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
            fwdPe: $this->average($peers, static fn (CompanyData $p): ?float => $p->valuation->fwdPe?->value),
            evEbitda: $this->average($peers, static fn (CompanyData $p): ?float => $p->valuation->evEbitda?->value),
            fcfYieldPercent: $this->average($peers, static fn (CompanyData $p): ?float => $p->valuation->fcfYield?->value),
            divYieldPercent: $this->average($peers, static fn (CompanyData $p): ?float => $p->valuation->divYield?->value),
            marketCapBillions: $this->average(
                $peers,
                fn (CompanyData $p): ?float => $this->toMarketCapBillions($p)
            ),
            companiesIncluded: count($peers),
        );
    }

    /**
     * Calculate average of non-null values.
     *
     * @param array<string, CompanyData> $peers
     * @param callable(CompanyData): ?float $extractor
     */
    private function average(array $peers, callable $extractor): ?float
    {
        $values = array_filter(
            array_map($extractor, $peers),
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
