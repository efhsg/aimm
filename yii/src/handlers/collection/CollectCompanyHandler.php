<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectCompanyRequest;
use app\dto\CollectCompanyResult;
use app\dto\CollectDatapointRequest;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\SourceAttempt;
use app\dto\SourceCandidate;
use app\dto\ValuationData;
use app\enums\CollectionStatus;
use app\enums\Severity;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
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
        $requiredKeys = $request->requirements->requiredValuationMetrics;
        $optionalKeys = $request->requirements->optionalValuationMetrics;
        $allMetricKeys = array_merge($requiredKeys, $optionalKeys);

        foreach ($allMetricKeys as $metric) {
            if ($this->isTimedOut($deadline)) {
                break;
            }

            $severity = in_array($metric, $requiredKeys, true)
                ? Severity::Required
                : Severity::Optional;

            $result = $this->datapointCollector->collect(new CollectDatapointRequest(
                datapointKey: "valuation.{$metric}",
                sourceCandidates: $sources,
                adapterId: 'chain',
                severity: $severity,
                ticker: $request->ticker,
            ));

            $metrics[$metric] = $result->datapoint;
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

        return new FinancialsData(
            historyYears: $request->requirements->historyYears,
            annualData: [],
        );
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

        return new QuartersData(quarters: []);
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

        $missingRequired = 0;
        foreach ($request->requirements->requiredValuationMetrics as $metric) {
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
    ): DataPointMoney|DataPointRatio|DataPointPercent|null {
        return match ($metric) {
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
    }
}
