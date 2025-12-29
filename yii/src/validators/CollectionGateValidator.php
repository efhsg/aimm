<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\GateError;
use app\dto\GateResult;
use app\dto\GateWarning;
use app\dto\IndustryConfig;
use app\dto\IndustryDataPack;
use app\dto\MetricDefinition;
use app\dto\QuarterFinancials;
use app\enums\CollectionMethod;
use DateTimeImmutable;

/**
 * Validates IndustryDataPack before analysis phase.
 */
final class CollectionGateValidator implements CollectionGateValidatorInterface
{
    private const ERROR_SCHEMA_INVALID = 'SCHEMA_INVALID';
    private const ERROR_MISSING_COMPANY = 'MISSING_COMPANY';
    private const ERROR_MISSING_REQUIRED = 'MISSING_REQUIRED';
    private const ERROR_MISSING_REQUIRED_FINANCIAL = 'MISSING_REQUIRED_FINANCIAL';
    private const ERROR_MISSING_REQUIRED_QUARTER = 'MISSING_REQUIRED_QUARTER';
    private const ERROR_MISSING_REQUIRED_OPERATIONAL = 'MISSING_REQUIRED_OPERATIONAL';
    private const ERROR_UNDOCUMENTED_MISSING = 'UNDOCUMENTED_MISSING';
    private const ERROR_MISSING_PROVENANCE = 'MISSING_PROVENANCE';
    private const ERROR_MISSING_ATTEMPTS = 'MISSING_ATTEMPTS';
    private const ERROR_MACRO_STALE = 'MACRO_STALE';

    private const WARNING_EXTRA_COMPANY = 'EXTRA_COMPANY';
    private const WARNING_MACRO_AGING = 'MACRO_AGING';
    private const WARNING_TEMPORAL_SPREAD = 'TEMPORAL_SPREAD';
    private const WARNING_LOW_COVERAGE = 'LOW_COVERAGE';

    public function __construct(
        private SchemaValidatorInterface $schemaValidator,
        private SemanticValidatorInterface $semanticValidator,
        private int $macroStalenessThresholdDays = 10,
    ) {
    }

    public function validate(IndustryDataPack $dataPack, IndustryConfig $config): GateResult
    {
        $errors = [];
        $warnings = [];

        // 1. Schema validation
        $schemaErrors = $this->validateSchema($dataPack);
        $errors = array_merge($errors, $schemaErrors);

        // If schema fails, return early
        if (count($schemaErrors) > 0) {
            return new GateResult(
                passed: false,
                errors: $errors,
                warnings: $warnings,
            );
        }

        // 2. Semantic validation
        $semanticResult = $this->semanticValidator->validate($dataPack);
        $errors = array_merge($errors, $semanticResult->errors);
        $warnings = array_merge($warnings, $semanticResult->warnings);

        // 3. Company completeness
        $companyErrors = $this->validateCompanyCompleteness($dataPack, $config);
        $errors = array_merge($errors, $companyErrors);

        // 4. Required datapoints (valuation)
        $requiredErrors = $this->validateRequiredDatapoints($dataPack, $config);
        $errors = array_merge($errors, $requiredErrors);

        // 5. Required financial metrics
        $financialErrors = $this->validateRequiredFinancials($dataPack, $config);
        $errors = array_merge($errors, $financialErrors);

        // 6. Required quarter metrics
        $quarterErrors = $this->validateRequiredQuarters($dataPack, $config);
        $errors = array_merge($errors, $quarterErrors);

        // 7. Required operational metrics
        $operationalErrors = $this->validateRequiredOperational($dataPack, $config);
        $errors = array_merge($errors, $operationalErrors);

        // 8. Provenance validation
        $provenanceErrors = $this->validateProvenance($dataPack);
        $errors = array_merge($errors, $provenanceErrors);

        // 9. Macro freshness
        $macroErrors = $this->validateMacroFreshness($dataPack);
        $errors = array_merge($errors, $macroErrors);

        // 10. Warnings
        $warnings = array_merge($warnings, $this->checkWarnings($dataPack, $config));

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }

    /**
     * @return list<GateError>
     */
    private function validateSchema(IndustryDataPack $dataPack): array
    {
        $result = $this->schemaValidator->validate(
            json_encode($dataPack->toArray()),
            'industry-datapack.schema.json'
        );

        if (!$result->isValid()) {
            return [
                new GateError(
                    code: self::ERROR_SCHEMA_INVALID,
                    message: 'DataPack failed JSON Schema validation: ' . implode(', ', $result->getErrors()),
                ),
            ];
        }

        return [];
    }

    /**
     * @return list<GateError>
     */
    private function validateCompanyCompleteness(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $errors = [];
        $configuredTickers = array_map(static fn ($c) => $c->ticker, $config->companies);
        $collectedTickers = array_keys($dataPack->companies);

        foreach ($configuredTickers as $ticker) {
            if (!in_array($ticker, $collectedTickers, true)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_COMPANY,
                    message: "Configured company {$ticker} not found in datapack",
                    path: "companies.{$ticker}",
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateRequiredDatapoints(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $errors = [];
        $requiredMetrics = $this->filterRequiredMetrics($config->dataRequirements->valuationMetrics);

        foreach ($dataPack->companies as $ticker => $company) {
            foreach ($requiredMetrics as $metric) {
                $datapoint = $this->getValuationMetric($company, $metric);

                if ($datapoint === null) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED,
                        message: "Required metric {$metric} is null for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$metric}",
                    );
                    continue;
                }

                if ($datapoint->value === null) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED,
                        message: "Required metric {$metric} has null value for {$ticker} (method: {$datapoint->method->value})",
                        path: "companies.{$ticker}.valuation.{$metric}.value",
                    );
                }

                // Check that not_found has attempted_sources
                if ($datapoint->method === CollectionMethod::NotFound) {
                    if (empty($datapoint->attemptedSources)) {
                        $errors[] = new GateError(
                            code: self::ERROR_UNDOCUMENTED_MISSING,
                            message: "Not-found metric {$metric} lacks attempted_sources for {$ticker}",
                            path: "companies.{$ticker}.valuation.{$metric}.attempted_sources",
                        );
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateRequiredFinancials(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $errors = [];
        $requiredMetrics = $this->filterRequiredMetrics($config->dataRequirements->annualFinancialMetrics);

        if (empty($requiredMetrics)) {
            return $errors;
        }

        $historyYears = $config->dataRequirements->historyYears;
        $currentYear = (int) date('Y');
        $startYear = $currentYear - $historyYears + 1;

        foreach ($dataPack->companies as $ticker => $company) {
            foreach ($requiredMetrics as $metric) {
                // Check at least one year of data exists
                $hasData = false;
                for ($year = $currentYear; $year >= $startYear; $year--) {
                    $annual = $company->financials->annualData[$year] ?? null;
                    if ($annual === null) {
                        continue;
                    }

                    $value = $this->getFinancialMetric($annual, $metric);
                    if ($value !== null && $value->value !== null) {
                        $hasData = true;
                        break;
                    }
                }

                if (!$hasData) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED_FINANCIAL,
                        message: "Required financial metric {$metric} has no data for {$ticker}",
                        path: "companies.{$ticker}.financials.{$metric}",
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateRequiredQuarters(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $errors = [];
        $requiredMetrics = $this->filterRequiredMetrics($config->dataRequirements->quarterMetrics);

        if (empty($requiredMetrics)) {
            return $errors;
        }

        $quartersToFetch = $config->dataRequirements->quartersToFetch;

        foreach ($dataPack->companies as $ticker => $company) {
            foreach ($requiredMetrics as $metric) {
                $foundCount = 0;
                foreach ($company->quarters->quarters as $quarter) {
                    $value = $this->getQuarterMetric($quarter, $metric);
                    if ($value !== null && $value->value !== null) {
                        $foundCount++;
                    }
                }

                if ($foundCount === 0) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED_QUARTER,
                        message: "Required quarter metric {$metric} has no data for {$ticker}",
                        path: "companies.{$ticker}.quarters.{$metric}",
                    );
                } elseif ($foundCount < $quartersToFetch) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED_QUARTER,
                        message: "Required quarter metric {$metric} has {$foundCount}/{$quartersToFetch} quarters for {$ticker}",
                        path: "companies.{$ticker}.quarters.{$metric}",
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateRequiredOperational(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $errors = [];
        $requiredMetrics = $this->filterRequiredMetrics($config->dataRequirements->operationalMetrics);

        if (empty($requiredMetrics)) {
            return $errors;
        }

        foreach ($dataPack->companies as $ticker => $company) {
            if ($company->operational === null) {
                foreach ($requiredMetrics as $metric) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED_OPERATIONAL,
                        message: "Required operational metric {$metric} is missing for {$ticker}",
                        path: "companies.{$ticker}.operational.{$metric}",
                    );
                }
                continue;
            }

            foreach ($requiredMetrics as $metric) {
                $datapoint = $company->operational->getMetric($metric);
                if ($datapoint === null || $datapoint->value === null) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_REQUIRED_OPERATIONAL,
                        message: "Required operational metric {$metric} is null for {$ticker}",
                        path: "companies.{$ticker}.operational.{$metric}",
                    );
                }
            }
        }

        return $errors;
    }

    private function getFinancialMetric(
        AnnualFinancials $annual,
        string $metric
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber|null {
        return match ($metric) {
            'revenue' => $annual->revenue,
            'ebitda' => $annual->ebitda,
            'net_income' => $annual->netIncome,
            'net_debt' => $annual->netDebt,
            'free_cash_flow' => $annual->freeCashFlow,
            default => $annual->additionalMetrics[$metric] ?? null,
        };
    }

    private function getQuarterMetric(
        QuarterFinancials $quarter,
        string $metric
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber|null {
        return match ($metric) {
            'revenue' => $quarter->revenue,
            'ebitda' => $quarter->ebitda,
            'net_income' => $quarter->netIncome,
            'free_cash_flow' => $quarter->freeCashFlow,
            default => $quarter->additionalMetrics[$metric] ?? null,
        };
    }

    /**
     * @return list<GateError>
     */
    private function validateProvenance(IndustryDataPack $dataPack): array
    {
        $errors = [];

        // Validate macro datapoints
        $errors = array_merge($errors, $this->validateMacroProvenance($dataPack));

        // Validate company datapoints
        foreach ($dataPack->companies as $ticker => $company) {
            $errors = array_merge($errors, $this->validateCompanyProvenance($ticker, $company));
            $errors = array_merge($errors, $this->validateFinancialsProvenance($ticker, $company));
            $errors = array_merge($errors, $this->validateQuartersProvenance($ticker, $company));
            $errors = array_merge($errors, $this->validateOperationalProvenance($ticker, $company));
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateCompanyProvenance(string $ticker, CompanyData $company): array
    {
        $errors = [];
        $valuation = $company->valuation;

        $metrics = [
            'market_cap' => $valuation->marketCap,
            'fwd_pe' => $valuation->fwdPe,
            'trailing_pe' => $valuation->trailingPe,
            'ev_ebitda' => $valuation->evEbitda,
            'free_cash_flow_ttm' => $valuation->freeCashFlowTtm,
            'fcf_yield' => $valuation->fcfYield,
            'div_yield' => $valuation->divYield,
            'net_debt_ebitda' => $valuation->netDebtEbitda,
            'price_to_book' => $valuation->priceToBook,
        ];

        foreach ($metrics as $name => $datapoint) {
            if ($datapoint === null) {
                continue;
            }

            // WebFetch datapoints must have provenance
            if ($datapoint->method === CollectionMethod::WebFetch) {
                if (empty($datapoint->sourceUrl)) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_PROVENANCE,
                        message: "WebFetch datapoint {$name} lacks source_url for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.source_url",
                    );
                }
                if ($datapoint->sourceLocator === null) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_PROVENANCE,
                        message: "WebFetch datapoint {$name} lacks source_locator for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.source_locator",
                    );
                }
            }

            // Not-found datapoints must have attempted_sources
            if ($datapoint->method === CollectionMethod::NotFound) {
                if ($datapoint->value !== null) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_PROVENANCE,
                        message: "Not-found datapoint {$name} has non-null value for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.value",
                    );
                }
                if (empty($datapoint->attemptedSources)) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_ATTEMPTS,
                        message: "Not-found datapoint {$name} lacks attempted_sources for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.attempted_sources",
                    );
                }
            }

            // Derived datapoints must have derivation info
            if ($datapoint->method === CollectionMethod::Derived) {
                if (empty($datapoint->derivedFrom)) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_PROVENANCE,
                        message: "Derived datapoint {$name} lacks derived_from for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.derived_from",
                    );
                }
                if (empty($datapoint->formula)) {
                    $errors[] = new GateError(
                        code: self::ERROR_MISSING_PROVENANCE,
                        message: "Derived datapoint {$name} lacks formula for {$ticker}",
                        path: "companies.{$ticker}.valuation.{$name}.formula",
                    );
                }
            }
        }

        foreach ($valuation->additionalMetrics as $name => $datapoint) {
            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "companies.{$ticker}.valuation.additional_metrics.{$name}")
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateMacroProvenance(IndustryDataPack $dataPack): array
    {
        $errors = [];
        $macro = $dataPack->macro;

        $datapoints = [
            'commodity_benchmark' => $macro->commodityBenchmark,
            'margin_proxy' => $macro->marginProxy,
            'sector_index' => $macro->sectorIndex,
        ];

        foreach ($datapoints as $name => $datapoint) {
            if ($datapoint === null) {
                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "macro.{$name}")
            );
        }

        // Validate additional indicators
        foreach ($macro->additionalIndicators as $indicatorName => $datapoint) {
            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance(
                    $datapoint,
                    "macro.additional_indicators.{$indicatorName}"
                )
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateFinancialsProvenance(string $ticker, CompanyData $company): array
    {
        $errors = [];

        foreach ($company->financials->annualData as $annual) {
            $errors = array_merge(
                $errors,
                $this->validateAnnualFinancialsProvenance($ticker, $annual)
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateAnnualFinancialsProvenance(string $ticker, AnnualFinancials $annual): array
    {
        $errors = [];
        $year = $annual->fiscalYear;
        $basePath = "companies.{$ticker}.financials.annual_data.{$year}";

        $datapoints = [
            'revenue' => $annual->revenue,
            'ebitda' => $annual->ebitda,
            'net_income' => $annual->netIncome,
            'net_debt' => $annual->netDebt,
            'free_cash_flow' => $annual->freeCashFlow,
        ];

        foreach ($datapoints as $name => $datapoint) {
            if ($datapoint === null) {
                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "{$basePath}.{$name}")
            );
        }

        foreach ($annual->additionalMetrics as $name => $datapoint) {
            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "{$basePath}.additional_metrics.{$name}")
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateQuartersProvenance(string $ticker, CompanyData $company): array
    {
        $errors = [];

        foreach ($company->quarters->quarters as $quarterKey => $quarter) {
            $errors = array_merge(
                $errors,
                $this->validateQuarterFinancialsProvenance($ticker, $quarterKey, $quarter)
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateQuarterFinancialsProvenance(
        string $ticker,
        string $quarterKey,
        QuarterFinancials $quarter
    ): array {
        $errors = [];
        $basePath = "companies.{$ticker}.quarters.quarters.{$quarterKey}";

        $datapoints = [
            'revenue' => $quarter->revenue,
            'ebitda' => $quarter->ebitda,
            'net_income' => $quarter->netIncome,
            'free_cash_flow' => $quarter->freeCashFlow,
        ];

        foreach ($datapoints as $name => $datapoint) {
            if ($datapoint === null) {
                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "{$basePath}.{$name}")
            );
        }

        foreach ($quarter->additionalMetrics as $name => $datapoint) {
            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance($datapoint, "{$basePath}.additional_metrics.{$name}")
            );
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateOperationalProvenance(string $ticker, CompanyData $company): array
    {
        $errors = [];

        if ($company->operational === null) {
            return $errors;
        }

        foreach ($company->operational->metrics as $metricName => $datapoint) {
            $errors = array_merge(
                $errors,
                $this->validateDatapointProvenance(
                    $datapoint,
                    "companies.{$ticker}.operational.{$metricName}"
                )
            );
        }

        return $errors;
    }

    /**
     * Generic provenance validation for any datapoint type.
     *
     * @return list<GateError>
     */
    private function validateDatapointProvenance(
        DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber $datapoint,
        string $path
    ): array {
        $errors = [];

        // WebFetch/WebSearch/Api datapoints must have provenance
        if (in_array($datapoint->method, [
            CollectionMethod::WebFetch,
            CollectionMethod::WebSearch,
            CollectionMethod::Api,
        ], true)) {
            if (empty($datapoint->sourceUrl)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Datapoint at {$path} lacks source_url for method {$datapoint->method->value}",
                    path: "{$path}.source_url",
                );
            }
            if ($datapoint->sourceLocator === null) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Datapoint at {$path} lacks source_locator for method {$datapoint->method->value}",
                    path: "{$path}.source_locator",
                );
            }
        }

        // Not-found datapoints must have attempted_sources and null value
        if ($datapoint->method === CollectionMethod::NotFound) {
            if ($datapoint->value !== null) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Not-found datapoint at {$path} has non-null value",
                    path: "{$path}.value",
                );
            }
            if (empty($datapoint->attemptedSources)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_ATTEMPTS,
                    message: "Not-found datapoint at {$path} lacks attempted_sources",
                    path: "{$path}.attempted_sources",
                );
            }
        }

        // Derived datapoints must have derivation info
        if ($datapoint->method === CollectionMethod::Derived) {
            if (empty($datapoint->derivedFrom)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Derived datapoint at {$path} lacks derived_from",
                    path: "{$path}.derived_from",
                );
            }
            if (empty($datapoint->formula)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Derived datapoint at {$path} lacks formula",
                    path: "{$path}.formula",
                );
            }
        }

        // Cache datapoints must have cache info
        if ($datapoint->method === CollectionMethod::Cache) {
            if (empty($datapoint->cacheSource)) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Cache datapoint at {$path} lacks cache_source",
                    path: "{$path}.cache_source",
                );
            }
            if ($datapoint->cacheAgeDays === null) {
                $errors[] = new GateError(
                    code: self::ERROR_MISSING_PROVENANCE,
                    message: "Cache datapoint at {$path} lacks cache_age_days",
                    path: "{$path}.cache_age_days",
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<GateError>
     */
    private function validateMacroFreshness(IndustryDataPack $dataPack): array
    {
        $errors = [];
        $now = new DateTimeImmutable();
        $threshold = $this->macroStalenessThresholdDays;

        $macroDatapoints = [
            'commodity_benchmark' => $dataPack->macro->commodityBenchmark,
            'margin_proxy' => $dataPack->macro->marginProxy,
        ];

        foreach ($macroDatapoints as $name => $datapoint) {
            if ($datapoint === null || $datapoint->value === null) {
                continue;
            }

            $age = $now->diff($datapoint->retrievedAt)->days;

            if ($age > $threshold) {
                $errors[] = new GateError(
                    code: self::ERROR_MACRO_STALE,
                    message: "Macro datapoint {$name} is {$age} days old (threshold: {$threshold})",
                    path: "macro.{$name}",
                );
            }
        }

        return $errors;
    }

    /**
     * @return list<GateWarning>
     */
    private function checkWarnings(IndustryDataPack $dataPack, IndustryConfig $config): array
    {
        $warnings = [];

        // Extra companies
        $configuredTickers = array_map(static fn ($c) => $c->ticker, $config->companies);
        foreach (array_keys($dataPack->companies) as $ticker) {
            if (!in_array($ticker, $configuredTickers, true)) {
                $warnings[] = new GateWarning(
                    code: self::WARNING_EXTRA_COMPANY,
                    message: "Unexpected company {$ticker} in datapack",
                    path: "companies.{$ticker}",
                );
            }
        }

        // Macro aging (approaching staleness)
        $now = new DateTimeImmutable();
        $threshold = $this->macroStalenessThresholdDays;
        $warningThreshold = (int) ($threshold * 0.8);

        if ($dataPack->macro->commodityBenchmark?->retrievedAt !== null) {
            $age = $now->diff($dataPack->macro->commodityBenchmark->retrievedAt)->days;
            if ($age > $warningThreshold && $age <= $threshold) {
                $warnings[] = new GateWarning(
                    code: self::WARNING_MACRO_AGING,
                    message: "Macro commodity_benchmark is {$age} days old, approaching staleness",
                    path: 'macro.commodity_benchmark',
                );
            }
        }

        // Temporal spread
        $collectionDuration = $dataPack->collectionLog->durationSeconds;
        if ($collectionDuration > 86400) { // > 24 hours
            $warnings[] = new GateWarning(
                code: self::WARNING_TEMPORAL_SPREAD,
                message: "Collection span exceeds 24 hours ({$collectionDuration}s)",
                path: 'collection_log.duration_seconds',
            );
        }

        // Low coverage on optional metrics
        $optionalMetrics = $this->filterOptionalMetrics($config->dataRequirements->valuationMetrics);
        foreach ($optionalMetrics as $metric) {
            $coverage = $this->calculateCoverage($dataPack, $metric);
            if ($coverage < 0.5) {
                $warnings[] = new GateWarning(
                    code: self::WARNING_LOW_COVERAGE,
                    message: "Optional metric {$metric} has " . round($coverage * 100, 1) . '% coverage',
                    path: "valuation.{$metric}",
                );
            }
        }

        return $warnings;
    }

    private function getValuationMetric(
        CompanyData $company,
        string $metric
    ): DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber|null {
        $known = match ($metric) {
            'market_cap' => $company->valuation->marketCap,
            'fwd_pe' => $company->valuation->fwdPe,
            'trailing_pe' => $company->valuation->trailingPe,
            'ev_ebitda' => $company->valuation->evEbitda,
            'free_cash_flow_ttm' => $company->valuation->freeCashFlowTtm,
            'fcf_yield' => $company->valuation->fcfYield,
            'div_yield' => $company->valuation->divYield,
            'net_debt_ebitda' => $company->valuation->netDebtEbitda,
            'price_to_book' => $company->valuation->priceToBook,
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        return $company->valuation->additionalMetrics[$metric] ?? null;
    }

    /**
     * @param list<MetricDefinition> $metrics
     * @return list<string>
     */
    private function filterRequiredMetrics(array $metrics): array
    {
        $required = [];
        foreach ($metrics as $metric) {
            if ($metric->required) {
                $required[] = $metric->key;
            }
        }

        return $required;
    }

    /**
     * @param list<MetricDefinition> $metrics
     * @return list<string>
     */
    private function filterOptionalMetrics(array $metrics): array
    {
        $optional = [];
        foreach ($metrics as $metric) {
            if (!$metric->required) {
                $optional[] = $metric->key;
            }
        }

        return $optional;
    }

    private function calculateCoverage(IndustryDataPack $dataPack, string $metric): float
    {
        $total = count($dataPack->companies);
        if ($total === 0) {
            return 0.0;
        }

        $found = 0;
        foreach ($dataPack->companies as $company) {
            $datapoint = $this->getValuationMetric($company, $metric);
            if ($datapoint?->value !== null) {
                $found++;
            }
        }

        return $found / $total;
    }
}
