<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\AnnualFinancials;
use app\dto\CollectCompanyRequest;
use app\dto\CollectCompanyResult;
use app\dto\CollectDatapointRequest;
use app\dto\CollectDatapointResult;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\FinancialsData;
use app\dto\MetricDefinition;
use app\dto\OperationalData;
use app\dto\QuarterFinancials;
use app\dto\QuartersData;
use app\dto\SourceAttempt;
use app\dto\SourceCandidate;
use app\dto\ValuationData;
use app\enums\CollectionStatus;
use app\enums\Severity;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use DateTimeImmutable;
use yii\log\Logger;

/**
 * Orchestrates collection of all financial data for a single company.
 *
 * Coordinates valuation metrics, financials, and quarterly data collection
 * by delegating to CollectDatapointHandler for each metric.
 */
final class CollectCompanyHandler implements CollectCompanyInterface
{
    public function __construct(
        private readonly CollectDatapointInterface $datapointCollector,
        private readonly SourceCandidateFactory $sourceCandidateFactory,
        private readonly DataPointFactory $dataPointFactory,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectCompanyRequest $request): CollectCompanyResult
    {
        $startTime = microtime(true);
        $deadline = $startTime + $request->maxDurationSeconds;

        $this->logger->log(
            [
                'message' => 'Starting company collection',
                'ticker' => $request->ticker,
                'max_duration_seconds' => $request->maxDurationSeconds,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $allAttempts = [];
        $timedOut = false;

        $sources = $this->sourceCandidateFactory->forTicker(
            $request->ticker,
            $request->config->listingExchange
        );

        $valuation = $this->collectValuationMetrics(
            $request,
            $sources,
            $allAttempts,
            $deadline
        );

        if ($this->isTimedOut($deadline)) {
            $timedOut = true;
        }

        $financials = null;
        if (!$timedOut) {
            $financials = $this->collectFinancials(
                $request,
                $sources,
                $allAttempts,
                $deadline
            );

            if ($this->isTimedOut($deadline)) {
                $timedOut = true;
            }
        }

        $quarters = null;
        if (!$timedOut) {
            $quarters = $this->collectQuarters(
                $request,
                $sources,
                $allAttempts,
                $deadline
            );

            if ($this->isTimedOut($deadline)) {
                $timedOut = true;
            }
        }

        $operational = null;
        if (!$timedOut && !empty($request->requirements->operationalMetrics)) {
            $operational = $this->collectOperationalMetrics(
                $request,
                $sources,
                $allAttempts,
                $deadline
            );
        }

        $financials ??= new FinancialsData(
            historyYears: $request->requirements->historyYears,
            annualData: [],
        );
        $quarters ??= new QuartersData(quarters: []);

        $status = $timedOut
            ? CollectionStatus::Partial
            : $this->determineStatus($valuation, $financials, $request);

        $elapsedSeconds = microtime(true) - $startTime;
        $this->logger->log(
            [
                'message' => 'Company collection complete',
                'ticker' => $request->ticker,
                'status' => $status->value,
                'attempts' => count($allAttempts),
                'elapsed_seconds' => round($elapsedSeconds, 2),
                'timed_out' => $timedOut,
            ],
            $timedOut ? Logger::LEVEL_WARNING : Logger::LEVEL_INFO,
            'collection'
        );

        return new CollectCompanyResult(
            ticker: $request->ticker,
            data: new CompanyData(
                ticker: $request->ticker,
                name: $request->config->name,
                listingExchange: $request->config->listingExchange,
                listingCurrency: $request->config->listingCurrency,
                reportingCurrency: $request->config->reportingCurrency,
                valuation: $valuation,
                financials: $financials,
                quarters: $quarters,
                operational: $operational,
            ),
            sourceAttempts: $allAttempts,
            status: $status,
        );
    }

    /**
     * @param list<SourceCandidate> $sources
     * @param SourceAttempt[] $allAttempts
     */
    private function collectValuationMetrics(
        CollectCompanyRequest $request,
        array $sources,
        array &$allAttempts,
        float $deadline
    ): ValuationData {
        $metrics = [];
        $definitions = $request->requirements->valuationMetrics;

        foreach ($definitions as $definition) {
            if ($this->isTimedOut($deadline)) {
                break;
            }

            $severity = $definition->required ? Severity::Required : Severity::Optional;

            $result = $this->datapointCollector->collect(new CollectDatapointRequest(
                datapointKey: "valuation.{$definition->key}",
                sourceCandidates: $sources,
                adapterId: 'chain',
                severity: $severity,
                ticker: $request->ticker,
                unit: $definition->unit,
            ));

            $metrics[$definition->key] = $result->datapoint;
            $allAttempts = array_merge($allAttempts, $result->sourceAttempts);
        }

        $fcfYield = $this->calculateFcfYield(
            $metrics,
            $request->ticker
        );

        return $this->buildValuationData($metrics, $fcfYield);
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     */
    private function calculateFcfYield(
        array $metrics,
        string $ticker
    ): ?DataPointPercent {
        $marketCap = $metrics['market_cap'] ?? null;
        $freeCashFlowTtm = $metrics['free_cash_flow_ttm'] ?? null;

        if (!$marketCap instanceof DataPointMoney || !$freeCashFlowTtm instanceof DataPointMoney) {
            return null;
        }

        if ($marketCap->value === null || $freeCashFlowTtm->value === null) {
            return null;
        }

        if ($marketCap->currency !== $freeCashFlowTtm->currency) {
            return null;
        }

        $marketCapBase = $marketCap->getBaseValue();
        $freeCashFlowBase = $freeCashFlowTtm->getBaseValue();

        if ($marketCapBase === null || $marketCapBase == 0.0 || $freeCashFlowBase === null) {
            return null;
        }

        $datapoint = $this->dataPointFactory->derived(
            unit: 'percent',
            value: ($freeCashFlowBase / $marketCapBase) * 100,
            derivedFrom: [
                "/companies/{$ticker}/valuation/free_cash_flow_ttm",
                "/companies/{$ticker}/valuation/market_cap",
            ],
            formula: '(free_cash_flow_ttm / market_cap) * 100',
        );

        if (!$datapoint instanceof DataPointPercent) {
            return null;
        }

        return $datapoint;
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     */
    private function buildValuationData(
        array $metrics,
        ?DataPointPercent $fcfYield
    ): ValuationData {
        $marketCap = $metrics['market_cap'] ?? null;

        if (!$marketCap instanceof DataPointMoney) {
            $marketCap = $this->dataPointFactory->notFound('currency', ['No sources available']);
            if (!$marketCap instanceof DataPointMoney) {
                throw new \LogicException('Expected DataPointMoney for not found market_cap');
            }
        }

        $additionalMetrics = $this->extractAdditionalMetrics($metrics);

        return new ValuationData(
            marketCap: $marketCap,
            fwdPe: $this->getAsRatio($metrics, 'fwd_pe'),
            trailingPe: $this->getAsRatio($metrics, 'trailing_pe'),
            evEbitda: $this->getAsRatio($metrics, 'ev_ebitda'),
            freeCashFlowTtm: $this->getAsMoney($metrics, 'free_cash_flow_ttm'),
            fcfYield: $fcfYield ?? $this->getAsPercent($metrics, 'fcf_yield'),
            divYield: $this->getAsPercent($metrics, 'div_yield'),
            netDebtEbitda: $this->getAsRatio($metrics, 'net_debt_ebitda'),
            priceToBook: $this->getAsRatio($metrics, 'price_to_book'),
            additionalMetrics: $additionalMetrics,
        );
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     */
    private function getAsRatio(array $metrics, string $key): ?DataPointRatio
    {
        $datapoint = $metrics[$key] ?? null;
        if (!$datapoint instanceof DataPointRatio) {
            return null;
        }
        return $datapoint->value !== null ? $datapoint : null;
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     */
    private function getAsMoney(array $metrics, string $key): ?DataPointMoney
    {
        $datapoint = $metrics[$key] ?? null;
        if (!$datapoint instanceof DataPointMoney) {
            return null;
        }
        return $datapoint->value !== null ? $datapoint : null;
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     */
    private function getAsPercent(array $metrics, string $key): ?DataPointPercent
    {
        $datapoint = $metrics[$key] ?? null;
        if (!$datapoint instanceof DataPointPercent) {
            return null;
        }
        return $datapoint->value !== null ? $datapoint : null;
    }

    /**
     * @param list<SourceCandidate> $sources
     * @param SourceAttempt[] $allAttempts
     */
    private function collectFinancials(
        CollectCompanyRequest $request,
        array $sources,
        array &$allAttempts,
        float $deadline
    ): FinancialsData {
        if ($this->isTimedOut($deadline)) {
            return new FinancialsData(
                historyYears: $request->requirements->historyYears,
                annualData: [],
            );
        }

        $financialsSources = $this->sourceCandidateFactory->forFinancials(
            $request->ticker,
            $request->config->listingExchange
        );

        if (empty($financialsSources)) {
            return new FinancialsData(
                historyYears: $request->requirements->historyYears,
                annualData: [],
            );
        }

        $annualData = [];
        $definitions = $request->requirements->annualFinancialMetrics;

        // Collect each financial metric
        $metricResults = [];
        foreach ($definitions as $definition) {
            if ($this->isTimedOut($deadline)) {
                break;
            }

            $severity = $definition->required ? Severity::Required : Severity::Optional;
            $datapointKey = "financials.{$definition->key}";

            $result = $this->datapointCollector->collect(new CollectDatapointRequest(
                datapointKey: $datapointKey,
                sourceCandidates: $financialsSources,
                adapterId: 'chain',
                severity: $severity,
                ticker: $request->ticker,
                unit: $definition->unit,
            ));

            $metricResults[$definition->key] = $result;
            $allAttempts = array_merge($allAttempts, $result->sourceAttempts);
        }

        // Parse the historical data into AnnualFinancials by fiscal year
        $annualData = $this->buildAnnualFinancials(
            $metricResults,
            $request->requirements->historyYears,
            $request->ticker
        );

        return new FinancialsData(
            historyYears: $request->requirements->historyYears,
            annualData: $annualData,
        );
    }

    /**
     * @param array<string, CollectDatapointResult> $metricResults
     * @return array<int, AnnualFinancials>
     */
    private function buildAnnualFinancials(
        array $metricResults,
        int $historyYears,
        string $ticker
    ): array {
        // Group period data by year across all metrics
        /** @var array<int, array<string, DataPointMoney>> $periodsByYear */
        $periodsByYear = [];

        foreach ($metricResults as $metricKey => $result) {
            if (!$result->found) {
                continue;
            }

            // Check for historical extraction with period data
            if ($result->isHistorical() && $result->historicalExtraction !== null && $result->fetchResult !== null) {
                $datapoints = $this->dataPointFactory->fromHistoricalExtractionByYear(
                    $result->historicalExtraction,
                    $result->fetchResult,
                    $historyYears
                );

                foreach ($datapoints as $year => $datapoint) {
                    $periodsByYear[$year][$metricKey] = $datapoint;
                }
            }
        }

        // Build AnnualFinancials for each year
        $annualData = [];

        // Sort years descending
        krsort($periodsByYear);

        $count = 0;
        foreach ($periodsByYear as $year => $yearMetrics) {
            if ($count >= $historyYears) {
                break;
            }

            if (empty($yearMetrics)) {
                continue;
            }

            $annualData[$year] = new AnnualFinancials(
                fiscalYear: $year,
                revenue: $this->getAsMoney($yearMetrics, 'revenue'),
                ebitda: $this->getAsMoney($yearMetrics, 'ebitda'),
                netIncome: $this->getAsMoney($yearMetrics, 'net_income'),
                netDebt: $this->getAsMoney($yearMetrics, 'net_debt'),
                freeCashFlow: $this->getAsMoney($yearMetrics, 'free_cash_flow'),
                additionalMetrics: $this->extractAdditionalFinancialMetrics($yearMetrics),
            );
            $count++;
        }

        return $annualData;
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     * @return array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber>
     */
    private function extractAdditionalFinancialMetrics(array $metrics): array
    {
        $knownKeys = ['revenue', 'ebitda', 'net_income', 'net_debt', 'free_cash_flow'];
        $knownLookup = array_fill_keys($knownKeys, true);
        $additional = [];

        foreach ($metrics as $key => $datapoint) {
            if (isset($knownLookup[$key])) {
                continue;
            }
            $additional[$key] = $datapoint;
        }

        return $additional;
    }

    /**
     * @param list<SourceCandidate> $sources
     * @param SourceAttempt[] $allAttempts
     */
    private function collectQuarters(
        CollectCompanyRequest $request,
        array $sources,
        array &$allAttempts,
        float $deadline
    ): QuartersData {
        if ($this->isTimedOut($deadline)) {
            return new QuartersData(quarters: []);
        }

        $quartersSources = $this->sourceCandidateFactory->forQuarters(
            $request->ticker,
            $request->config->listingExchange
        );

        if (empty($quartersSources)) {
            return new QuartersData(quarters: []);
        }

        $definitions = $request->requirements->quarterMetrics;

        // Collect each quarterly metric
        $metricResults = [];
        foreach ($definitions as $definition) {
            if ($this->isTimedOut($deadline)) {
                break;
            }

            $severity = $definition->required ? Severity::Required : Severity::Optional;
            $datapointKey = "quarters.{$definition->key}";

            $result = $this->datapointCollector->collect(new CollectDatapointRequest(
                datapointKey: $datapointKey,
                sourceCandidates: $quartersSources,
                adapterId: 'chain',
                severity: $severity,
                ticker: $request->ticker,
                unit: $definition->unit,
            ));

            $metricResults[$definition->key] = $result;
            $allAttempts = array_merge($allAttempts, $result->sourceAttempts);
        }

        // Parse the historical data into QuarterFinancials
        $quarters = $this->buildQuarterFinancials(
            $metricResults,
            $request->requirements->quartersToFetch,
            $request->ticker
        );

        return new QuartersData(quarters: $quarters);
    }

    /**
     * @param array<string, CollectDatapointResult> $metricResults
     * @return array<string, QuarterFinancials>
     */
    private function buildQuarterFinancials(
        array $metricResults,
        int $quartersToFetch,
        string $ticker
    ): array {
        // Group period data by quarter key (e.g., "2024Q3")
        /** @var array<string, array<string, DataPointMoney>> $periodsByQuarter */
        $periodsByQuarter = [];
        /** @var array<string, DateTimeImmutable> $quarterEndDates */
        $quarterEndDates = [];

        foreach ($metricResults as $metricKey => $result) {
            if (!$result->found) {
                continue;
            }

            // Check for historical extraction with period data
            if ($result->isHistorical() && $result->historicalExtraction !== null && $result->fetchResult !== null) {
                $datapoints = $this->dataPointFactory->fromHistoricalExtractionByQuarter(
                    $result->historicalExtraction,
                    $result->fetchResult,
                    $quartersToFetch
                );

                foreach ($datapoints as $quarterKey => $datapoint) {
                    $periodsByQuarter[$quarterKey][$metricKey] = $datapoint;
                    // Store the actual end date from the datapoint
                    if ($datapoint->asOf !== null && !isset($quarterEndDates[$quarterKey])) {
                        $quarterEndDates[$quarterKey] = $datapoint->asOf;
                    }
                }
            }
        }

        // Build QuarterFinancials for each quarter
        $quarters = [];

        // Sort by quarter key descending (most recent first)
        krsort($periodsByQuarter);

        $count = 0;
        foreach ($periodsByQuarter as $quarterKey => $quarterMetrics) {
            if ($count >= $quartersToFetch) {
                break;
            }

            // Parse quarter key "2024Q3" -> year=2024, quarter=3
            if (!preg_match('/^(\d{4})Q(\d)$/', $quarterKey, $matches)) {
                continue;
            }

            if (empty($quarterMetrics)) {
                continue;
            }

            $year = (int) $matches[1];
            $quarter = (int) $matches[2];
            $periodEnd = $quarterEndDates[$quarterKey] ?? $this->getQuarterEndDate($year, $quarter);

            $quarters[$quarterKey] = new QuarterFinancials(
                fiscalYear: $year,
                fiscalQuarter: $quarter,
                periodEnd: $periodEnd,
                revenue: $this->getAsMoney($quarterMetrics, 'revenue'),
                ebitda: $this->getAsMoney($quarterMetrics, 'ebitda'),
                netIncome: $this->getAsMoney($quarterMetrics, 'net_income'),
                freeCashFlow: $this->getAsMoney($quarterMetrics, 'free_cash_flow'),
                additionalMetrics: $this->extractAdditionalFinancialMetrics($quarterMetrics),
            );
            $count++;
        }

        return $quarters;
    }

    private function getQuarterEndDate(int $year, int $quarter): DateTimeImmutable
    {
        return match ($quarter) {
            1 => new DateTimeImmutable("{$year}-03-31"),
            2 => new DateTimeImmutable("{$year}-06-30"),
            3 => new DateTimeImmutable("{$year}-09-30"),
            4 => new DateTimeImmutable("{$year}-12-31"),
            default => new DateTimeImmutable("{$year}-12-31"),
        };
    }

    /**
     * @param list<SourceCandidate> $sources
     * @param SourceAttempt[] $allAttempts
     */
    private function collectOperationalMetrics(
        CollectCompanyRequest $request,
        array $sources,
        array &$allAttempts,
        float $deadline
    ): ?OperationalData {
        $definitions = $request->requirements->operationalMetrics;

        if (empty($definitions)) {
            return null;
        }

        $metrics = [];
        foreach ($definitions as $definition) {
            if ($this->isTimedOut($deadline)) {
                break;
            }

            $severity = $definition->required ? Severity::Required : Severity::Optional;
            $datapointKey = "operational.{$definition->key}";

            $result = $this->datapointCollector->collect(new CollectDatapointRequest(
                datapointKey: $datapointKey,
                sourceCandidates: $sources,
                adapterId: 'chain',
                severity: $severity,
                ticker: $request->ticker,
                unit: $definition->unit,
            ));

            if ($result->found && $result->datapoint->value !== null) {
                $datapoint = $result->datapoint;
                if ($datapoint instanceof DataPointMoney
                    || $datapoint instanceof DataPointNumber
                    || $datapoint instanceof DataPointPercent
                ) {
                    $metrics[$definition->key] = $datapoint;
                }
            }
            $allAttempts = array_merge($allAttempts, $result->sourceAttempts);
        }

        if (empty($metrics)) {
            return null;
        }

        return new OperationalData(metrics: $metrics);
    }

    private function isTimedOut(float $deadline): bool
    {
        return microtime(true) >= $deadline;
    }

    private function determineStatus(
        ValuationData $valuation,
        FinancialsData $financials,
        CollectCompanyRequest $request
    ): CollectionStatus {
        if ($valuation->marketCap->value === null) {
            return CollectionStatus::Failed;
        }

        $requiredKeys = $this->getRequiredMetricKeys($request->requirements->valuationMetrics);
        $missingRequired = 0;
        foreach ($requiredKeys as $metric) {
            $datapoint = $this->getValuationMetric($valuation, $metric);
            if ($datapoint === null || $datapoint->value === null) {
                $missingRequired++;
            }
        }

        if ($missingRequired > 0) {
            return CollectionStatus::Partial;
        }

        return CollectionStatus::Complete;
    }

    private function getValuationMetric(
        ValuationData $valuation,
        string $metric
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber|null {
        $known = match ($metric) {
            'market_cap' => $valuation->marketCap,
            'fwd_pe' => $valuation->fwdPe,
            'trailing_pe' => $valuation->trailingPe,
            'ev_ebitda' => $valuation->evEbitda,
            'free_cash_flow_ttm' => $valuation->freeCashFlowTtm,
            'fcf_yield' => $valuation->fcfYield,
            'div_yield' => $valuation->divYield,
            'net_debt_ebitda' => $valuation->netDebtEbitda,
            'price_to_book' => $valuation->priceToBook,
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        return $valuation->additionalMetrics[$metric] ?? null;
    }

    /**
     * @param list<MetricDefinition> $definitions
     * @return list<string>
     */
    private function getRequiredMetricKeys(array $definitions): array
    {
        $required = [];
        foreach ($definitions as $definition) {
            if ($definition->required) {
                $required[] = $definition->key;
            }
        }

        return $required;
    }

    /**
     * @param array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber> $metrics
     * @return array<string, DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber>
     */
    private function extractAdditionalMetrics(array $metrics): array
    {
        $knownKeys = [
            'market_cap',
            'fwd_pe',
            'trailing_pe',
            'ev_ebitda',
            'free_cash_flow_ttm',
            'fcf_yield',
            'div_yield',
            'net_debt_ebitda',
            'price_to_book',
        ];

        $knownLookup = array_fill_keys($knownKeys, true);
        $additional = [];

        foreach ($metrics as $key => $datapoint) {
            if (isset($knownLookup[$key])) {
                continue;
            }
            $additional[$key] = $datapoint;
        }

        return $additional;
    }
}
