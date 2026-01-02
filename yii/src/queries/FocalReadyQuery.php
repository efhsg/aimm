<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\DataRequirements;
use app\dto\MetricDefinition;
use yii\db\Connection;

/**
 * Checks if a company has sufficient data in the dossier to be used as a focal.
 *
 * A company is "focal ready" when all SCOPE_FOCAL required metrics have non-null values.
 * Only metrics in the allowlist are checked. Derived metrics check their input dependencies.
 */
final class FocalReadyQuery
{
    /**
     * Allowlist: valuation metric keys → valuation_snapshot columns.
     */
    private const VALUATION_METRIC_MAP = [
        'market_cap' => 'market_cap',
        'fwd_pe' => 'forward_pe',
        'trailing_pe' => 'trailing_pe',
        'ev_ebitda' => 'ev_to_ebitda',
        'price_to_book' => 'price_to_book',
        'div_yield' => 'dividend_yield',
        'enterprise_value' => 'enterprise_value',
    ];

    /**
     * Allowlist: annual financial metric keys → annual_financial columns.
     */
    private const ANNUAL_METRIC_MAP = [
        'revenue' => 'revenue',
        'ebitda' => 'ebitda',
        'net_income' => 'net_income',
        'net_debt' => 'net_debt',
        'free_cash_flow' => 'free_cash_flow',
    ];

    /**
     * Allowlist: quarterly financial metric keys → quarterly_financial columns.
     */
    private const QUARTERLY_METRIC_MAP = [
        'revenue' => 'revenue',
        'ebitda' => 'ebitda',
        'net_income' => 'net_income',
        'free_cash_flow' => 'free_cash_flow',
    ];

    /**
     * Allowlist: TTM metric keys → ttm_financial columns.
     * These are metrics with _ttm suffix that map directly to TTM table.
     */
    private const TTM_METRIC_MAP = [
        'revenue_ttm' => 'revenue',
        'ebitda_ttm' => 'ebitda',
        'net_income_ttm' => 'net_income',
        'free_cash_flow_ttm' => 'free_cash_flow',
    ];

    /**
     * Derived metrics and their required inputs.
     * Format: metric_key => [[table, column], [table, column], ...]
     */
    private const DERIVED_METRIC_INPUTS = [
        'fcf_yield' => [
            ['valuation_snapshot', 'market_cap'],
            ['ttm_financial', 'free_cash_flow'],
        ],
        'net_debt_ebitda' => [
            ['annual_financial', 'net_debt'],
            ['annual_financial', 'ebitda'],
        ],
    ];

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Check if a company is "focal ready" - has all SCOPE_FOCAL required metrics.
     */
    public function isFocalReady(int $companyId, DataRequirements $requirements): bool
    {
        $missing = $this->getMissingFocalMetrics($companyId, $requirements);
        return empty($missing);
    }

    /**
     * Get list of missing SCOPE_FOCAL required metrics for a company.
     *
     * @return list<string> List of missing metric keys
     */
    public function getMissingFocalMetrics(int $companyId, DataRequirements $requirements): array
    {
        $missing = [];

        // Check valuation metrics (includes TTM and derived metrics)
        $focalValuation = $this->filterFocalRequired($requirements->valuationMetrics);
        foreach ($focalValuation as $metric) {
            if ($this->isDerivedMetric($metric->key)) {
                if (!$this->hasDerivedMetricInputs($companyId, $metric->key, $requirements->historyYears)) {
                    $missing[] = "derived.{$metric->key}";
                }
            } elseif ($this->isTtmMetric($metric->key)) {
                if (!$this->hasTtmMetric($companyId, $metric->key)) {
                    $missing[] = "ttm.{$metric->key}";
                }
            } elseif (isset(self::VALUATION_METRIC_MAP[$metric->key])) {
                if (!$this->hasValuationMetric($companyId, $metric->key)) {
                    $missing[] = "valuation.{$metric->key}";
                }
            }
            // Unknown metrics are ignored (allowlist behavior)
        }

        // Check annual financial metrics
        $focalAnnual = $this->filterFocalRequired($requirements->annualFinancialMetrics);
        foreach ($focalAnnual as $metric) {
            if ($this->isDerivedMetric($metric->key)) {
                if (!$this->hasDerivedMetricInputs($companyId, $metric->key, $requirements->historyYears)) {
                    $missing[] = "derived.{$metric->key}";
                }
            } elseif (isset(self::ANNUAL_METRIC_MAP[$metric->key])) {
                if (!$this->hasAnnualMetric($companyId, $metric->key, $requirements->historyYears)) {
                    $missing[] = "annual.{$metric->key}";
                }
            }
        }

        // Check quarterly metrics
        $focalQuarterly = $this->filterFocalRequired($requirements->quarterMetrics);
        foreach ($focalQuarterly as $metric) {
            if ($this->isDerivedMetric($metric->key)) {
                if (!$this->hasDerivedMetricInputs($companyId, $metric->key, $requirements->historyYears)) {
                    $missing[] = "derived.{$metric->key}";
                }
            } elseif (isset(self::QUARTERLY_METRIC_MAP[$metric->key])) {
                if (!$this->hasQuarterlyMetric($companyId, $metric->key, $requirements->quartersToFetch)) {
                    $missing[] = "quarterly.{$metric->key}";
                }
            }
        }

        return $missing;
    }

    /**
     * Filter metrics to only those that are required with SCOPE_FOCAL.
     *
     * @param list<MetricDefinition> $metrics
     * @return list<MetricDefinition>
     */
    private function filterFocalRequired(array $metrics): array
    {
        return array_values(array_filter(
            $metrics,
            static fn (MetricDefinition $m): bool =>
                $m->required && $m->requiredScope === MetricDefinition::SCOPE_FOCAL
        ));
    }

    private function isDerivedMetric(string $metricKey): bool
    {
        return isset(self::DERIVED_METRIC_INPUTS[$metricKey]);
    }

    private function isTtmMetric(string $metricKey): bool
    {
        return isset(self::TTM_METRIC_MAP[$metricKey]);
    }

    /**
     * Check if all inputs for a derived metric are present.
     */
    private function hasDerivedMetricInputs(int $companyId, string $metricKey, int $historyYears): bool
    {
        $inputs = self::DERIVED_METRIC_INPUTS[$metricKey] ?? [];

        foreach ($inputs as [$table, $column]) {
            $hasInput = match ($table) {
                'valuation_snapshot' => $this->hasLatestValue($companyId, $table, $column, 'snapshot_date'),
                'ttm_financial' => $this->hasLatestValue($companyId, $table, $column, 'as_of_date'),
                'annual_financial' => $this->hasAnnualValue($companyId, $column, $historyYears),
                default => false,
            };

            if (!$hasInput) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if company has a valuation metric in the latest snapshot.
     */
    private function hasValuationMetric(int $companyId, string $metricKey): bool
    {
        $column = self::VALUATION_METRIC_MAP[$metricKey] ?? null;
        if ($column === null) {
            return true; // Unknown metrics ignored
        }

        return $this->hasLatestValue($companyId, 'valuation_snapshot', $column, 'snapshot_date');
    }

    /**
     * Check if company has a TTM metric in the latest TTM record.
     */
    private function hasTtmMetric(int $companyId, string $metricKey): bool
    {
        $column = self::TTM_METRIC_MAP[$metricKey] ?? null;
        if ($column === null) {
            return true; // Unknown metrics ignored
        }

        return $this->hasLatestValue($companyId, 'ttm_financial', $column, 'as_of_date');
    }

    /**
     * Generic check for latest row with non-null value.
     */
    private function hasLatestValue(int $companyId, string $table, string $column, string $dateColumn): bool
    {
        $value = $this->db->createCommand(
            "SELECT {$column}
             FROM {$table}
             WHERE company_id = :companyId
             ORDER BY {$dateColumn} DESC
             LIMIT 1"
        )
            ->bindValue(':companyId', $companyId)
            ->queryScalar();

        return $value !== false && $value !== null;
    }

    /**
     * Check if company has at least one year of annual data with the metric.
     */
    private function hasAnnualMetric(int $companyId, string $metricKey, int $historyYears): bool
    {
        $column = self::ANNUAL_METRIC_MAP[$metricKey] ?? null;
        if ($column === null) {
            return true; // Unknown metrics ignored
        }

        return $this->hasAnnualValue($companyId, $column, $historyYears);
    }

    /**
     * Check annual_financial for at least 1 year with non-null value.
     */
    private function hasAnnualValue(int $companyId, string $column, int $historyYears): bool
    {
        $currentYear = (int) date('Y');
        $startYear = $currentYear - $historyYears + 1;

        $count = $this->db->createCommand(
            "SELECT COUNT(*)
             FROM annual_financial
             WHERE company_id = :companyId
               AND fiscal_year >= :startYear
               AND fiscal_year <= :currentYear
               AND {$column} IS NOT NULL
               AND is_current = 1"
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':startYear' => $startYear,
                ':currentYear' => $currentYear,
            ])
            ->queryScalar();

        return (int) $count > 0;
    }

    /**
     * Check if company has sufficient quarters of data with the metric.
     *
     * Validates that the latest N quarters all have non-null values.
     */
    private function hasQuarterlyMetric(int $companyId, string $metricKey, int $quartersToFetch): bool
    {
        $column = self::QUARTERLY_METRIC_MAP[$metricKey] ?? null;
        if ($column === null) {
            return true; // Unknown metrics ignored
        }

        // Count non-null values in the latest N quarters
        $count = $this->db->createCommand(
            "SELECT COUNT(*)
             FROM (
                 SELECT {$column}
                 FROM quarterly_financial
                 WHERE company_id = :companyId
                   AND is_current = 1
                 ORDER BY fiscal_year DESC, fiscal_quarter DESC
                 LIMIT :limit
             ) AS latest_quarters
             WHERE {$column} IS NOT NULL"
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':limit' => $quartersToFetch,
            ])
            ->queryScalar();

        return (int) $count >= $quartersToFetch;
    }
}
