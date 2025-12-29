<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\HistoricalExtraction;
use app\dto\PeriodValue;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Centralizes creation of provenance-heavy datapoint objects.
 */
final class DataPointFactory
{
    /**
     * Create DataPoint from successful extraction.
     */
    public function fromExtraction(
        Extraction $extraction,
        ?FetchResult $fetchResult = null
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber {
        if ($extraction->isFromCache()) {
            return $this->fromCache(
                unit: $extraction->unit,
                value: $extraction->rawValue,
                originalAsOf: $extraction->asOf ?? new DateTimeImmutable(),
                cacheSource: $extraction->cacheSource ?? '',
                cacheAgeDays: $extraction->cacheAgeDays ?? 0,
                sourceLocator: $extraction->locator,
                currency: $extraction->currency,
                scale: $extraction->scale,
            );
        }

        if ($fetchResult === null) {
            throw new InvalidArgumentException(
                'FetchResult is required for non-cache extractions'
            );
        }

        $asOf = $extraction->asOf ?? new DateTimeImmutable($fetchResult->retrievedAt->format('Y-m-d'));

        return match ($extraction->unit) {
            'currency' => new DataPointMoney(
                value: $extraction->rawValue,
                currency: $extraction->currency ?? 'USD',
                scale: $this->parseScale($extraction->scale),
                asOf: $asOf,
                sourceUrl: $fetchResult->finalUrl,
                retrievedAt: $fetchResult->retrievedAt,
                method: CollectionMethod::WebFetch,
                sourceLocator: $extraction->locator,
            ),
            'ratio' => new DataPointRatio(
                value: $extraction->rawValue,
                asOf: $asOf,
                sourceUrl: $fetchResult->finalUrl,
                retrievedAt: $fetchResult->retrievedAt,
                method: CollectionMethod::WebFetch,
                sourceLocator: $extraction->locator,
            ),
            'percent' => new DataPointPercent(
                value: $extraction->rawValue,
                asOf: $asOf,
                sourceUrl: $fetchResult->finalUrl,
                retrievedAt: $fetchResult->retrievedAt,
                method: CollectionMethod::WebFetch,
                sourceLocator: $extraction->locator,
            ),
            default => new DataPointNumber(
                value: $extraction->rawValue,
                asOf: $asOf,
                sourceUrl: $fetchResult->finalUrl,
                retrievedAt: $fetchResult->retrievedAt,
                method: CollectionMethod::WebFetch,
                sourceLocator: $extraction->locator,
            ),
        };
    }

    /**
     * Create DataPoint for not-found data.
     *
     * @param list<string> $attemptedSources
     */
    public function notFound(
        string $unit,
        array $attemptedSources,
        ?string $currency = null
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber {
        $now = new DateTimeImmutable();

        return match ($unit) {
            'currency' => new DataPointMoney(
                value: null,
                currency: $currency ?? 'USD',
                scale: DataScale::Units,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::NotFound,
                attemptedSources: $attemptedSources,
            ),
            'ratio' => new DataPointRatio(
                value: null,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::NotFound,
                attemptedSources: $attemptedSources,
            ),
            'percent' => new DataPointPercent(
                value: null,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::NotFound,
                attemptedSources: $attemptedSources,
            ),
            default => new DataPointNumber(
                value: null,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::NotFound,
                attemptedSources: $attemptedSources,
            ),
        };
    }

    /**
     * Create derived DataPoint from calculation.
     *
     * @param list<string> $derivedFrom
     */
    public function derived(
        string $unit,
        float $value,
        array $derivedFrom,
        string $formula,
        ?string $currency = null
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber {
        $now = new DateTimeImmutable();

        return match ($unit) {
            'currency' => new DataPointMoney(
                value: $value,
                currency: $currency ?? 'USD',
                scale: DataScale::Units,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::Derived,
                derivedFrom: $derivedFrom,
                formula: $formula,
            ),
            'ratio' => new DataPointRatio(
                value: $value,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::Derived,
                derivedFrom: $derivedFrom,
                formula: $formula,
            ),
            'percent' => new DataPointPercent(
                value: $value,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::Derived,
                derivedFrom: $derivedFrom,
                formula: $formula,
            ),
            default => new DataPointNumber(
                value: $value,
                asOf: $now,
                sourceUrl: null,
                retrievedAt: $now,
                method: CollectionMethod::Derived,
                derivedFrom: $derivedFrom,
                formula: $formula,
            ),
        };
    }

    /**
     * Create DataPoint from cached data with proper cache provenance.
     */
    public function fromCache(
        string $unit,
        float|int|null $value,
        DateTimeImmutable $originalAsOf,
        string $cacheSource,
        int $cacheAgeDays,
        ?SourceLocator $sourceLocator = null,
        ?string $currency = null,
        ?string $scale = null
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber {
        $now = new DateTimeImmutable();

        return match ($unit) {
            'currency' => new DataPointMoney(
                value: $value,
                currency: $currency ?? 'USD',
                scale: $this->parseScale($scale),
                asOf: $originalAsOf,
                sourceUrl: $cacheSource,
                retrievedAt: $now,
                method: CollectionMethod::Cache,
                sourceLocator: $sourceLocator,
                cacheSource: $cacheSource,
                cacheAgeDays: $cacheAgeDays,
            ),
            'ratio' => new DataPointRatio(
                value: $value,
                asOf: $originalAsOf,
                sourceUrl: $cacheSource,
                retrievedAt: $now,
                method: CollectionMethod::Cache,
                sourceLocator: $sourceLocator,
                cacheSource: $cacheSource,
                cacheAgeDays: $cacheAgeDays,
            ),
            'percent' => new DataPointPercent(
                value: $value,
                asOf: $originalAsOf,
                sourceUrl: $cacheSource,
                retrievedAt: $now,
                method: CollectionMethod::Cache,
                sourceLocator: $sourceLocator,
                cacheSource: $cacheSource,
                cacheAgeDays: $cacheAgeDays,
            ),
            default => new DataPointNumber(
                value: $value,
                asOf: $originalAsOf,
                sourceUrl: $cacheSource,
                retrievedAt: $now,
                method: CollectionMethod::Cache,
                sourceLocator: $sourceLocator,
                cacheSource: $cacheSource,
                cacheAgeDays: $cacheAgeDays,
            ),
        };
    }

    /**
     * Create DataPoints from historical extraction, keyed by fiscal year.
     *
     * @return array<int, DataPointMoney>
     */
    public function fromHistoricalExtractionByYear(
        HistoricalExtraction $extraction,
        FetchResult $fetchResult,
        int $maxYears
    ): array {
        $result = [];
        $byYear = $extraction->getByYear();
        $count = 0;

        foreach ($byYear as $year => $periods) {
            if ($count >= $maxYears) {
                break;
            }

            // For annual data, take the most recent period in the year
            $period = $periods[0];
            $result[$year] = $this->createDataPointForPeriod(
                $period,
                $extraction,
                $fetchResult
            );
            $count++;
        }

        return $result;
    }

    /**
     * Create DataPoints from historical extraction, keyed by quarter key (e.g., "2024Q3").
     *
     * @return array<string, DataPointMoney>
     */
    public function fromHistoricalExtractionByQuarter(
        HistoricalExtraction $extraction,
        FetchResult $fetchResult,
        int $maxQuarters
    ): array {
        $result = [];
        $byQuarter = $extraction->getByQuarter();
        $count = 0;

        foreach ($byQuarter as $quarterKey => $period) {
            if ($count >= $maxQuarters) {
                break;
            }

            $result[$quarterKey] = $this->createDataPointForPeriod(
                $period,
                $extraction,
                $fetchResult
            );
            $count++;
        }

        return $result;
    }

    public function fromHistoricalExtractionMostRecent(
        HistoricalExtraction $extraction,
        FetchResult $fetchResult
    ): ?DataPointMoney {
        $mostRecent = null;

        foreach ($extraction->periods as $period) {
            if ($mostRecent === null || $period->endDate > $mostRecent->endDate) {
                $mostRecent = $period;
            }
        }

        if ($mostRecent === null) {
            return null;
        }

        return $this->createDataPointForPeriod(
            $mostRecent,
            $extraction,
            $fetchResult
        );
    }

    private function createDataPointForPeriod(
        PeriodValue $period,
        HistoricalExtraction $extraction,
        FetchResult $fetchResult
    ): DataPointMoney {
        return new DataPointMoney(
            value: $period->value,
            currency: $extraction->currency ?? 'USD',
            scale: $this->parseScale($extraction->scale),
            asOf: $period->endDate,
            sourceUrl: $fetchResult->finalUrl,
            retrievedAt: $fetchResult->retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $extraction->locator,
        );
    }

    private function parseScale(?string $scale): DataScale
    {
        if ($scale === null) {
            return DataScale::Units;
        }

        return match ($scale) {
            'units' => DataScale::Units,
            'thousands' => DataScale::Thousands,
            'millions' => DataScale::Millions,
            'billions' => DataScale::Billions,
            'trillions' => DataScale::Trillions,
            default => DataScale::Units,
        };
    }
}
