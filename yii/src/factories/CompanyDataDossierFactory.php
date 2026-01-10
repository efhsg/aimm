<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\FinancialsData;
use app\dto\QuarterFinancials;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\queries\AnnualFinancialQuery;
use app\queries\CompanyQuery;
use app\queries\QuarterlyFinancialQuery;
use app\queries\TtmFinancialQuery;
use app\queries\ValuationSnapshotQuery;
use DateTimeImmutable;

/**
 * Builds CompanyData DTOs from dossier database records.
 */
final class CompanyDataDossierFactory implements CompanyDataDossierFactoryInterface
{
    private const DEFAULT_HISTORY_YEARS = 5;
    private const MAX_QUARTERS = 8;

    public function __construct(
        private readonly CompanyQuery $companyQuery,
        private readonly ValuationSnapshotQuery $valuationQuery,
        private readonly AnnualFinancialQuery $annualQuery,
        private readonly QuarterlyFinancialQuery $quarterlyQuery,
        private readonly TtmFinancialQuery $ttmQuery,
        private readonly DataPointFactory $dataPointFactory,
    ) {
    }

    /**
     * Build a CompanyData DTO from a company row.
     *
     * @param array<string, mixed> $companyRow From CompanyQuery::findByIndustry()
     */
    public function createFromDossier(array $companyRow): ?CompanyData
    {
        $companyId = (int) $companyRow['id'];
        $ticker = $companyRow['ticker'];
        $name = $companyRow['name'] ?? $ticker;

        $company = $this->companyQuery->findById($companyId);
        if ($company === null) {
            return null;
        }

        $valuation = $this->buildValuationData($companyId, $company);
        $financials = $this->buildFinancialsData($companyId);
        $quarters = $this->buildQuartersData($companyId);

        // Require at least market cap for a valid company
        if ($valuation->marketCap->value === null) {
            return null;
        }

        return new CompanyData(
            ticker: $ticker,
            name: $name,
            listingExchange: $company['exchange'] ?? 'UNKNOWN',
            listingCurrency: $company['currency'] ?? 'USD',
            reportingCurrency: $company['currency'] ?? 'USD',
            valuation: $valuation,
            financials: $financials,
            quarters: $quarters,
        );
    }

    /**
     * @param array<string, mixed> $company
     */
    private function buildValuationData(int $companyId, array $company): ValuationData
    {
        $snapshot = $this->valuationQuery->findLatestByCompany($companyId);
        $currency = $company['currency'] ?? 'USD';

        if ($snapshot === null) {
            return new ValuationData(
                marketCap: $this->dataPointFactory->notFound('currency', ['dossier'], $currency),
            );
        }

        $collectedAt = $snapshot['collected_at'] ?? $snapshot['snapshot_date'] ?? null;

        return new ValuationData(
            marketCap: $this->moneyFromDossier($snapshot['market_cap'], $currency, $collectedAt),
            fwdPe: $this->ratioFromDossier($snapshot['forward_pe'] ?? null, $collectedAt),
            trailingPe: $this->ratioFromDossier($snapshot['trailing_pe'] ?? null, $collectedAt),
            evEbitda: $this->ratioFromDossier($snapshot['ev_to_ebitda'] ?? null, $collectedAt),
            freeCashFlowTtm: $this->loadTtmFreeCashFlow($companyId, $currency),
            fcfYield: $this->percentFromDossier($snapshot['fcf_yield'] ?? null, $collectedAt),
            divYield: $this->percentFromDossier($snapshot['dividend_yield'] ?? null, $collectedAt),
            netDebtEbitda: $this->ratioFromDossier($snapshot['net_debt_to_ebitda'] ?? null, $collectedAt),
            priceToBook: $this->ratioFromDossier($snapshot['price_to_book'] ?? null, $collectedAt),
        );
    }

    private function loadTtmFreeCashFlow(int $companyId, string $currency): ?DataPointMoney
    {
        $now = new DateTimeImmutable();

        // First try TTM table
        $ttm = $this->ttmQuery->findByCompanyAndDate($companyId, $now);
        if ($ttm !== null && isset($ttm['free_cash_flow']) && $ttm['free_cash_flow'] !== null) {
            return $this->moneyFromDossier(
                $ttm['free_cash_flow'],
                $ttm['currency'] ?? $currency,
                $ttm['calculated_at'] ?? null
            );
        }

        // Fallback: sum last 4 quarters
        $quarters = $this->quarterlyQuery->findLastFourQuarters($companyId, $now);
        if (count($quarters) >= 4) {
            $sum = 0.0;
            $latestDate = null;
            foreach ($quarters as $q) {
                if (isset($q['free_cash_flow']) && $q['free_cash_flow'] !== null) {
                    $sum += (float) $q['free_cash_flow'];
                    if ($latestDate === null) {
                        $latestDate = $q['collected_at'] ?? $q['period_end_date'] ?? null;
                    }
                }
            }
            if ($sum !== 0.0) {
                return $this->moneyFromDossier($sum, $currency, $latestDate);
            }
        }

        return null;
    }

    private function buildFinancialsData(int $companyId): FinancialsData
    {
        $annuals = $this->annualQuery->findAllCurrentByCompany($companyId);
        $mapped = [];

        foreach ($annuals as $row) {
            if (count($mapped) >= self::DEFAULT_HISTORY_YEARS) {
                break;
            }

            $year = (int) $row['fiscal_year'];
            $currency = $row['currency'] ?? 'USD';
            $collectedAt = $row['collected_at'] ?? null;

            $mapped[$year] = new AnnualFinancials(
                fiscalYear: $year,
                periodEndDate: isset($row['period_end_date'])
                    ? new DateTimeImmutable($row['period_end_date'])
                    : null,
                revenue: $this->moneyFromDossier($row['revenue'] ?? null, $currency, $collectedAt),
                grossProfit: $this->moneyFromDossier($row['gross_profit'] ?? null, $currency, $collectedAt),
                operatingIncome: $this->moneyFromDossier($row['operating_income'] ?? null, $currency, $collectedAt),
                ebitda: $this->moneyFromDossier($row['ebitda'] ?? null, $currency, $collectedAt),
                netIncome: $this->moneyFromDossier($row['net_income'] ?? null, $currency, $collectedAt),
                freeCashFlow: $this->moneyFromDossier($row['free_cash_flow'] ?? null, $currency, $collectedAt),
                totalAssets: $this->moneyFromDossier($row['total_assets'] ?? null, $currency, $collectedAt),
                totalLiabilities: $this->moneyFromDossier($row['total_liabilities'] ?? null, $currency, $collectedAt),
                totalEquity: $this->moneyFromDossier($row['total_equity'] ?? null, $currency, $collectedAt),
                totalDebt: $this->moneyFromDossier($row['total_debt'] ?? null, $currency, $collectedAt),
                cashAndEquivalents: $this->moneyFromDossier($row['cash_and_equivalents'] ?? null, $currency, $collectedAt),
                netDebt: $this->moneyFromDossier($row['net_debt'] ?? null, $currency, $collectedAt),
                sharesOutstanding: $this->numberFromDossier($row['shares_outstanding'] ?? null, $collectedAt),
            );
        }

        return new FinancialsData(
            historyYears: self::DEFAULT_HISTORY_YEARS,
            annualData: $mapped,
        );
    }

    private function buildQuartersData(int $companyId): QuartersData
    {
        $quarters = $this->quarterlyQuery->findAllCurrentByCompany($companyId);
        $mapped = [];
        $count = 0;

        foreach ($quarters as $row) {
            if ($count >= self::MAX_QUARTERS) {
                break;
            }

            $year = (int) $row['fiscal_year'];
            $quarter = (int) $row['fiscal_quarter'];
            $key = "{$year}Q{$quarter}";
            $currency = $row['currency'] ?? 'USD';
            $collectedAt = $row['collected_at'] ?? null;

            $mapped[$key] = new QuarterFinancials(
                fiscalYear: $year,
                fiscalQuarter: $quarter,
                periodEnd: new DateTimeImmutable($row['period_end_date']),
                revenue: $this->moneyFromDossier($row['revenue'] ?? null, $currency, $collectedAt),
                ebitda: $this->moneyFromDossier($row['ebitda'] ?? null, $currency, $collectedAt),
                netIncome: $this->moneyFromDossier($row['net_income'] ?? null, $currency, $collectedAt),
                freeCashFlow: $this->moneyFromDossier($row['free_cash_flow'] ?? null, $currency, $collectedAt),
            );

            $count++;
        }

        return new QuartersData(quarters: $mapped);
    }

    private function moneyFromDossier(
        mixed $value,
        string $currency,
        ?string $collectedAt
    ): DataPointMoney {
        if ($value === null) {
            return $this->dataPointFactory->notFound('currency', ['dossier'], $currency);
        }

        $collected = $collectedAt !== null
            ? new DateTimeImmutable($collectedAt)
            : new DateTimeImmutable();
        $now = new DateTimeImmutable();
        $ageDays = (int) $now->diff($collected)->days;

        return $this->dataPointFactory->fromCache(
            unit: 'currency',
            value: (float) $value,
            originalAsOf: $collected,
            cacheSource: 'dossier',
            cacheAgeDays: $ageDays,
            currency: $currency,
        );
    }

    private function ratioFromDossier(
        mixed $value,
        ?string $collectedAt
    ): ?DataPointRatio {
        if ($value === null) {
            return null;
        }

        $collected = $collectedAt !== null
            ? new DateTimeImmutable($collectedAt)
            : new DateTimeImmutable();
        $now = new DateTimeImmutable();
        $ageDays = (int) $now->diff($collected)->days;

        return $this->dataPointFactory->fromCache(
            unit: 'ratio',
            value: (float) $value,
            originalAsOf: $collected,
            cacheSource: 'dossier',
            cacheAgeDays: $ageDays,
        );
    }

    private function percentFromDossier(
        mixed $value,
        ?string $collectedAt
    ): ?DataPointPercent {
        if ($value === null) {
            return null;
        }

        $collected = $collectedAt !== null
            ? new DateTimeImmutable($collectedAt)
            : new DateTimeImmutable();
        $now = new DateTimeImmutable();
        $ageDays = (int) $now->diff($collected)->days;

        return $this->dataPointFactory->fromCache(
            unit: 'percent',
            value: (float) $value,
            originalAsOf: $collected,
            cacheSource: 'dossier',
            cacheAgeDays: $ageDays,
        );
    }

    private function numberFromDossier(
        mixed $value,
        ?string $collectedAt
    ): ?DataPointNumber {
        if ($value === null) {
            return null;
        }

        $collected = $collectedAt !== null
            ? new DateTimeImmutable($collectedAt)
            : new DateTimeImmutable();
        $now = new DateTimeImmutable();
        $ageDays = (int) $now->diff($collected)->days;

        return $this->dataPointFactory->fromCache(
            unit: 'number',
            value: (float) $value,
            originalAsOf: $collected,
            cacheSource: 'dossier',
            cacheAgeDays: $ageDays,
        );
    }
}
