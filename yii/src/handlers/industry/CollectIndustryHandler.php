<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\CollectIndustryRequest as InternalCollectRequest;
use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\industry\CollectIndustryRequest;
use app\dto\industry\CollectIndustryResult;
use app\dto\IndustryConfig;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\dto\SourcePriorities;
use app\handlers\collection\CollectIndustryInterface as InternalCollector;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\IndustryQuery;
use Throwable;
use yii\log\Logger;

/**
 * Handler for collecting data for an industry.
 *
 * Abstracts the underlying collection internals, preventing UI
 * controllers from depending on collection implementation.
 */
final class CollectIndustryHandler implements CollectIndustryInterface
{
    private const DEFAULT_EXCHANGE = 'NASDAQ';
    private const DEFAULT_CURRENCY = 'USD';
    private const DEFAULT_FY_END_MONTH = 12;

    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly InternalCollector $industryCollector,
        private readonly CollectionRunRepository $runRepository,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectIndustryRequest $request): CollectIndustryResult
    {
        $this->logger->log(
            [
                'message' => 'Starting industry collection',
                'industry_id' => $request->industryId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        // 1. Load industry with sector info
        $industry = $this->industryQuery->findById($request->industryId);
        if ($industry === null) {
            return CollectIndustryResult::failure(['Industry not found.']);
        }

        if (!$industry['is_active']) {
            return CollectIndustryResult::failure(['Industry is not active.']);
        }

        // 2. Check for running collection
        if ($this->runRepository->hasRunningCollection($request->industryId)) {
            return CollectIndustryResult::failure(['A collection is already running for this industry.']);
        }

        // 3. Load policy (from industry or default)
        $policy = $this->resolvePolicy($industry);
        if ($policy === null) {
            return CollectIndustryResult::failure(['No collection policy configured for this industry.']);
        }

        // 4. Load companies directly by industry_id
        $companies = $this->companyQuery->findByIndustry($request->industryId);
        if (empty($companies)) {
            return CollectIndustryResult::failure(['Industry has no companies.']);
        }

        try {
            // 5. Build IndustryConfig from industry + policy + companies
            $industryConfig = $this->buildIndustryConfig($industry, $policy, $companies);

            // 6. Execute collection
            $result = $this->industryCollector->collect(new InternalCollectRequest(
                config: $industryConfig,
                batchSize: $request->batchSize,
                enableMemoryManagement: $request->enableMemoryManagement,
            ));

            // 7. Get run ID from datapack
            $run = $this->runRepository->findByDatapackId($result->datapackId);
            if ($run === null) {
                $this->logger->log(
                    [
                        'message' => 'Collection run record not found',
                        'industry_id' => $request->industryId,
                        'datapack_id' => $result->datapackId,
                    ],
                    Logger::LEVEL_ERROR,
                    'collection'
                );

                return CollectIndustryResult::failure(['Collection completed but run record not found.']);
            }

            $runId = (int) $run['id'];

            $this->logger->log(
                [
                    'message' => 'Industry collection completed',
                    'industry_id' => $request->industryId,
                    'run_id' => $runId,
                    'status' => $result->overallStatus->value,
                    'gate_passed' => $result->gateResult->passed,
                ],
                Logger::LEVEL_INFO,
                'collection'
            );

            return CollectIndustryResult::success(
                runId: $runId,
                datapackId: $result->datapackId,
                status: $result->overallStatus,
                gateResult: $result->gateResult,
            );
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Industry collection failed',
                    'industry_id' => $request->industryId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collection'
            );

            return CollectIndustryResult::failure(['Collection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Resolve the collection policy for the industry.
     *
     * @param array<string, mixed> $industry
     * @return array<string, mixed>|null
     */
    private function resolvePolicy(array $industry): ?array
    {
        // Use industry's assigned policy
        if (!empty($industry['policy_id'])) {
            return $this->policyQuery->findById((int) $industry['policy_id']);
        }

        return null;
    }

    /**
     * Build an IndustryConfig from industry data.
     *
     * @param array<string, mixed> $industry
     * @param array<string, mixed> $policy
     * @param list<array<string, mixed>> $companies
     */
    private function buildIndustryConfig(array $industry, array $policy, array $companies): IndustryConfig
    {
        $companyConfigs = [];
        foreach ($companies as $company) {
            $companyConfigs[] = $this->buildCompanyConfig($company);
        }

        $sourcePriorities = SourcePriorities::fromJson($policy['source_priorities'] ?? null);

        return new IndustryConfig(
            industryId: (int) $industry['id'],
            id: $industry['slug'],
            name: $industry['name'],
            sector: $industry['sector_name'],
            companies: $companyConfigs,
            macroRequirements: $this->buildMacroRequirements($policy),
            dataRequirements: $this->buildDataRequirements($policy),
            sourcePriorities: $sourcePriorities,
        );
    }

    /**
     * Build a CompanyConfig from company data.
     *
     * @param array<string, mixed> $company
     */
    private function buildCompanyConfig(array $company): CompanyConfig
    {
        $exchange = $company['exchange'] ?? self::DEFAULT_EXCHANGE;
        $currency = $company['currency'] ?? self::DEFAULT_CURRENCY;
        $fyEndMonth = $company['fiscal_year_end'] ?? self::DEFAULT_FY_END_MONTH;

        return new CompanyConfig(
            ticker: $company['ticker'],
            name: $company['name'] ?? $company['ticker'],
            listingExchange: $exchange,
            listingCurrency: $currency,
            reportingCurrency: $currency,
            fyEndMonth: (int) $fyEndMonth,
        );
    }

    /**
     * Build MacroRequirements from policy data.
     *
     * @param array<string, mixed> $policy
     */
    private function buildMacroRequirements(array $policy): MacroRequirements
    {
        return new MacroRequirements(
            commodityBenchmark: $policy['commodity_benchmark'] ?? null,
            marginProxy: $policy['margin_proxy'] ?? null,
            sectorIndex: $policy['sector_index'] ?? null,
            requiredIndicators: $this->decodeJson($policy['required_indicators'] ?? null) ?? [],
            optionalIndicators: $this->decodeJson($policy['optional_indicators'] ?? null) ?? [],
        );
    }

    /**
     * Build DataRequirements from policy data.
     *
     * @param array<string, mixed> $policy
     */
    private function buildDataRequirements(array $policy): DataRequirements
    {
        return new DataRequirements(
            historyYears: (int) ($policy['history_years'] ?? 5),
            quartersToFetch: (int) ($policy['quarters_to_fetch'] ?? 8),
            valuationMetrics: $this->parseMetrics($policy['valuation_metrics'] ?? null),
            annualFinancialMetrics: $this->parseMetrics($policy['annual_financial_metrics'] ?? null),
            quarterMetrics: $this->parseMetrics($policy['quarterly_financial_metrics'] ?? null),
            operationalMetrics: $this->parseMetrics($policy['operational_metrics'] ?? null),
        );
    }

    /**
     * Parse metric definitions from JSON column.
     *
     * @return list<MetricDefinition>
     */
    private function parseMetrics(mixed $value): array
    {
        $data = $this->decodeJson($value);
        if (!is_array($data)) {
            return [];
        }

        $metrics = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['key'])) {
                continue;
            }

            $metrics[] = new MetricDefinition(
                key: $item['key'],
                unit: $item['unit'] ?? MetricDefinition::UNIT_NUMBER,
                required: (bool) ($item['required'] ?? false),
                requiredScope: $item['required_scope'] ?? MetricDefinition::SCOPE_ALL,
            );
        }

        return $metrics;
    }

    /**
     * Decode JSON value, handling both string and already-decoded arrays.
     */
    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }
}
