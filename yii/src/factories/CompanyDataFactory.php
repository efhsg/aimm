<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\FxConversion;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\OperationalData;
use app\dto\QuarterFinancials;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\SourceLocatorType;
use DateTimeImmutable;

/**
 * Factory for reconstructing CompanyData and related DTOs from arrays.
 */
final class CompanyDataFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): CompanyData
    {
        return new CompanyData(
            ticker: $data['ticker'],
            name: $data['name'],
            listingExchange: $data['listing_exchange'],
            listingCurrency: $data['listing_currency'],
            reportingCurrency: $data['reporting_currency'],
            valuation: self::valuationFromArray($data['valuation']),
            financials: self::financialsFromArray($data['financials']),
            quarters: self::quartersFromArray($data['quarters']),
            operational: isset($data['operational']) ? self::operationalFromArray($data['operational']) : null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function valuationFromArray(array $data): ValuationData
    {
        $additionalMetrics = [];
        foreach ($data['additional_metrics'] ?? [] as $key => $metric) {
            $additionalMetrics[$key] = self::dataPointFromArray($metric);
        }

        return new ValuationData(
            marketCap: self::moneyFromArray($data['market_cap']),
            fwdPe: isset($data['fwd_pe']) ? self::ratioFromArray($data['fwd_pe']) : null,
            trailingPe: isset($data['trailing_pe']) ? self::ratioFromArray($data['trailing_pe']) : null,
            evEbitda: isset($data['ev_ebitda']) ? self::ratioFromArray($data['ev_ebitda']) : null,
            freeCashFlowTtm: isset($data['free_cash_flow_ttm']) ? self::moneyFromArray($data['free_cash_flow_ttm']) : null,
            fcfYield: isset($data['fcf_yield']) ? self::percentFromArray($data['fcf_yield']) : null,
            divYield: isset($data['div_yield']) ? self::percentFromArray($data['div_yield']) : null,
            netDebtEbitda: isset($data['net_debt_ebitda']) ? self::ratioFromArray($data['net_debt_ebitda']) : null,
            priceToBook: isset($data['price_to_book']) ? self::ratioFromArray($data['price_to_book']) : null,
            additionalMetrics: $additionalMetrics,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function financialsFromArray(array $data): FinancialsData
    {
        $annualData = [];
        foreach ($data['annual_data'] as $year => $annual) {
            $annualData[(int) $year] = self::annualFinancialsFromArray($annual);
        }

        return new FinancialsData(
            historyYears: $data['history_years'],
            annualData: $annualData,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function annualFinancialsFromArray(array $data): AnnualFinancials
    {
        $additionalMetrics = [];
        foreach ($data['additional_metrics'] ?? [] as $key => $metric) {
            $additionalMetrics[$key] = self::dataPointFromArray($metric);
        }

        return new AnnualFinancials(
            fiscalYear: $data['fiscal_year'],
            revenue: isset($data['revenue']) ? self::moneyFromArray($data['revenue']) : null,
            ebitda: isset($data['ebitda']) ? self::moneyFromArray($data['ebitda']) : null,
            netIncome: isset($data['net_income']) ? self::moneyFromArray($data['net_income']) : null,
            netDebt: isset($data['net_debt']) ? self::moneyFromArray($data['net_debt']) : null,
            freeCashFlow: isset($data['free_cash_flow']) ? self::moneyFromArray($data['free_cash_flow']) : null,
            additionalMetrics: $additionalMetrics,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function quartersFromArray(array $data): QuartersData
    {
        $quarters = [];
        foreach ($data['quarters'] as $key => $quarter) {
            $quarters[$key] = self::quarterFinancialsFromArray($quarter);
        }

        return new QuartersData(quarters: $quarters);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function quarterFinancialsFromArray(array $data): QuarterFinancials
    {
        $additionalMetrics = [];
        foreach ($data['additional_metrics'] ?? [] as $key => $metric) {
            $additionalMetrics[$key] = self::dataPointFromArray($metric);
        }

        return new QuarterFinancials(
            fiscalYear: $data['fiscal_year'],
            fiscalQuarter: $data['fiscal_quarter'],
            periodEnd: new DateTimeImmutable($data['period_end']),
            revenue: isset($data['revenue']) ? self::moneyFromArray($data['revenue']) : null,
            ebitda: isset($data['ebitda']) ? self::moneyFromArray($data['ebitda']) : null,
            netIncome: isset($data['net_income']) ? self::moneyFromArray($data['net_income']) : null,
            freeCashFlow: isset($data['free_cash_flow']) ? self::moneyFromArray($data['free_cash_flow']) : null,
            additionalMetrics: $additionalMetrics,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function operationalFromArray(array $data): OperationalData
    {
        $metrics = [];
        foreach ($data as $key => $metric) {
            $metrics[$key] = self::dataPointFromArray($metric);
        }

        return new OperationalData(metrics: $metrics);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function moneyFromArray(array $data): DataPointMoney
    {
        return new DataPointMoney(
            value: $data['value'],
            currency: $data['currency'],
            scale: DataScale::from($data['scale']),
            asOf: new DateTimeImmutable($data['as_of']),
            sourceUrl: $data['source_url'],
            retrievedAt: new DateTimeImmutable($data['retrieved_at']),
            method: CollectionMethod::from($data['method']),
            sourceLocator: isset($data['source_locator']) ? self::sourceLocatorFromArray($data['source_locator']) : null,
            attemptedSources: $data['attempted_sources'] ?? null,
            derivedFrom: $data['derived_from'] ?? null,
            formula: $data['formula'] ?? null,
            fxConversion: isset($data['fx_conversion']) ? self::fxConversionFromArray($data['fx_conversion']) : null,
            cacheSource: $data['cache_source'] ?? null,
            cacheAgeDays: $data['cache_age_days'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function ratioFromArray(array $data): DataPointRatio
    {
        return new DataPointRatio(
            value: $data['value'],
            asOf: new DateTimeImmutable($data['as_of']),
            sourceUrl: $data['source_url'],
            retrievedAt: new DateTimeImmutable($data['retrieved_at']),
            method: CollectionMethod::from($data['method']),
            sourceLocator: isset($data['source_locator']) ? self::sourceLocatorFromArray($data['source_locator']) : null,
            attemptedSources: $data['attempted_sources'] ?? null,
            derivedFrom: $data['derived_from'] ?? null,
            formula: $data['formula'] ?? null,
            cacheSource: $data['cache_source'] ?? null,
            cacheAgeDays: $data['cache_age_days'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function percentFromArray(array $data): DataPointPercent
    {
        return new DataPointPercent(
            value: $data['value'],
            asOf: new DateTimeImmutable($data['as_of']),
            sourceUrl: $data['source_url'],
            retrievedAt: new DateTimeImmutable($data['retrieved_at']),
            method: CollectionMethod::from($data['method']),
            sourceLocator: isset($data['source_locator']) ? self::sourceLocatorFromArray($data['source_locator']) : null,
            attemptedSources: $data['attempted_sources'] ?? null,
            derivedFrom: $data['derived_from'] ?? null,
            formula: $data['formula'] ?? null,
            cacheSource: $data['cache_source'] ?? null,
            cacheAgeDays: $data['cache_age_days'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function numberFromArray(array $data): DataPointNumber
    {
        return new DataPointNumber(
            value: $data['value'],
            asOf: new DateTimeImmutable($data['as_of']),
            sourceUrl: $data['source_url'],
            retrievedAt: new DateTimeImmutable($data['retrieved_at']),
            method: CollectionMethod::from($data['method']),
            sourceLocator: isset($data['source_locator']) ? self::sourceLocatorFromArray($data['source_locator']) : null,
            attemptedSources: $data['attempted_sources'] ?? null,
            derivedFrom: $data['derived_from'] ?? null,
            formula: $data['formula'] ?? null,
            cacheSource: $data['cache_source'] ?? null,
            cacheAgeDays: $data['cache_age_days'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function dataPointFromArray(array $data): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber
    {
        return match ($data['unit']) {
            'currency' => self::moneyFromArray($data),
            'ratio' => self::ratioFromArray($data),
            'percent' => self::percentFromArray($data),
            default => self::numberFromArray($data),
        };
    }

    /**
     * @param array<string, string> $data
     */
    public static function sourceLocatorFromArray(array $data): SourceLocator
    {
        return new SourceLocator(
            type: SourceLocatorType::from($data['type']),
            selector: $data['selector'],
            snippet: $data['snippet'],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fxConversionFromArray(array $data): FxConversion
    {
        return new FxConversion(
            originalCurrency: $data['original_currency'],
            originalValue: $data['original_value'],
            rate: $data['rate'],
            rateAsOf: new DateTimeImmutable($data['rate_as_of']),
            rateSource: $data['rate_source'],
        );
    }
}
