<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\ValuationData;
use app\queries\DataPackRepository;
use DateTimeImmutable;

final class CachedDataAdapter implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'cache';
    private const MAX_CACHE_AGE_DAYS = 7;

    private const SUPPORTED_KEYS = [
        'valuation.market_cap',
        'valuation.fwd_pe',
        'valuation.trailing_pe',
        'valuation.ev_ebitda',
        'valuation.div_yield',
        'valuation.fcf_yield',
        'valuation.net_debt_ebitda',
        'valuation.price_to_book',
    ];

    public function __construct(
        private readonly DataPackRepository $repository,
        private readonly string $industryId,
    ) {
    }

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        $latestPack = $this->repository->getLatest($this->industryId);

        if ($latestPack === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'No cached datapack available',
            );
        }

        $age = (int) (new DateTimeImmutable())->diff($latestPack->collectedAt)->days;
        if ($age > self::MAX_CACHE_AGE_DAYS) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: "Cached datapack too old ({$age} days)",
            );
        }

        $ticker = $request->ticker;
        if (!is_string($ticker) || $ticker === '' || !$latestPack->hasCompany($ticker)) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Ticker not in cached datapack',
            );
        }

        $company = $latestPack->getCompany($ticker);
        if ($company === null) {
            return new AdaptResult(
                adapterId: self::ADAPTER_ID,
                extractions: [],
                notFound: $request->datapointKeys,
                parseError: 'Ticker not in cached datapack',
            );
        }

        $extractions = [];
        $notFound = [];
        $cacheSource = $this->getCacheSource($latestPack->datapackId);

        foreach ($request->datapointKeys as $key) {
            if (!in_array($key, self::SUPPORTED_KEYS, true)) {
                $notFound[] = $key;
                continue;
            }

            $metric = str_replace('valuation.', '', $key);
            $datapoint = $this->getValuationMetric($company->valuation, $metric);

            if ($datapoint === null || $datapoint->value === null) {
                $notFound[] = $key;
                continue;
            }

            $currency = null;
            $scale = null;

            if ($datapoint instanceof DataPointMoney) {
                $currency = $datapoint->currency;
                $scale = $datapoint->scale->value;
            }

            $extractions[$key] = new Extraction(
                datapointKey: $key,
                rawValue: $datapoint->value,
                unit: $datapoint::UNIT,
                currency: $currency,
                scale: $scale,
                asOf: $datapoint->asOf,
                locator: SourceLocator::json(
                    $this->getCacheLocator($latestPack->datapackId, $ticker, $metric),
                    "Cached from datapack {$latestPack->datapackId} ({$age} days old)",
                ),
                cacheSource: $cacheSource,
                cacheAgeDays: $age,
            );
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $extractions,
            notFound: $notFound,
            parseError: count($extractions) > 0
                ? "Using cached data ({$age} days old) for " . count($extractions) . ' metrics'
                : null,
        );
    }

    private function getCacheSource(string $datapackId): string
    {
        return "cache://{$this->industryId}/{$datapackId}";
    }

    private function getCacheLocator(string $datapackId, string $ticker, string $metric): string
    {
        return "cache://{$this->industryId}/{$datapackId}/companies/{$ticker}/valuation/{$metric}";
    }

    private function getValuationMetric(
        ValuationData $valuation,
        string $metric
    ): DataPointMoney|DataPointRatio|DataPointPercent|null {
        return match ($metric) {
            'market_cap' => $valuation->marketCap,
            'fwd_pe' => $valuation->fwdPe,
            'trailing_pe' => $valuation->trailingPe,
            'ev_ebitda' => $valuation->evEbitda,
            'fcf_yield' => $valuation->fcfYield,
            'div_yield' => $valuation->divYield,
            'net_debt_ebitda' => $valuation->netDebtEbitda,
            'price_to_book' => $valuation->priceToBook,
            default => null,
        };
    }
}
