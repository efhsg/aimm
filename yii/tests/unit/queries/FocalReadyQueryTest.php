<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\DataRequirements;
use app\dto\MetricDefinition;
use app\queries\FocalReadyQuery;
use Codeception\Test\Unit;
use Yii;

/**
 * @covers \app\queries\FocalReadyQuery
 */
final class FocalReadyQueryTest extends Unit
{
    private FocalReadyQuery $query;
    private int $companyId;

    protected function _before(): void
    {
        $this->query = new FocalReadyQuery(Yii::$app->db);

        Yii::$app->db->createCommand()->delete('ttm_financial')->execute();
        Yii::$app->db->createCommand()->delete('quarterly_financial')->execute();
        Yii::$app->db->createCommand()->delete('annual_financial')->execute();
        Yii::$app->db->createCommand()->delete('valuation_snapshot')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();

        Yii::$app->db->createCommand()
            ->insert('company', [
                'ticker' => 'TEST',
                'name' => 'Test Company',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->execute();
        $this->companyId = (int) Yii::$app->db->getLastInsertID();
    }

    protected function _after(): void
    {
        Yii::$app->db->createCommand()->delete('ttm_financial')->execute();
        Yii::$app->db->createCommand()->delete('quarterly_financial')->execute();
        Yii::$app->db->createCommand()->delete('annual_financial')->execute();
        Yii::$app->db->createCommand()->delete('valuation_snapshot')->execute();
        Yii::$app->db->createCommand()->delete('company')->execute();
    }

    public function testIsFocalReadyReturnsTrueWhenNoFocalMetricsRequired(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_ALL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenValuationMetricMissing(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenValuationMetricPresent(): void
    {
        $this->insertValuationSnapshot(['market_cap' => 1000000000]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenTtmMetricMissing(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('revenue_ttm', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenTtmMetricPresent(): void
    {
        $this->insertTtmFinancial(['revenue' => 5000000000]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('revenue_ttm', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenDerivedMetricInputsMissing(): void
    {
        // fcf_yield requires market_cap (valuation) and free_cash_flow (ttm)
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('fcf_yield', MetricDefinition::UNIT_PERCENT, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenDerivedMetricInputsPresent(): void
    {
        // fcf_yield requires market_cap (valuation) and free_cash_flow (ttm)
        $this->insertValuationSnapshot(['market_cap' => 1000000000]);
        $this->insertTtmFinancial(['free_cash_flow' => 100000000]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('fcf_yield', MetricDefinition::UNIT_PERCENT, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenAnnualMetricMissing(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [
                new MetricDefinition('revenue', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenAnnualMetricPresent(): void
    {
        $this->insertAnnualFinancial((int) date('Y'), ['revenue' => 5000000000]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [
                new MetricDefinition('revenue', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenQuarterlyMetricMissing(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [],
            quarterMetrics: [
                new MetricDefinition('revenue', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenQuarterlyMetricPresent(): void
    {
        // Insert 4 quarters of data
        $year = (int) date('Y');
        for ($q = 1; $q <= 4; $q++) {
            $this->insertQuarterlyFinancial($year, $q, ['revenue' => 1000000000]);
        }

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [],
            quarterMetrics: [
                new MetricDefinition('revenue', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyReturnsFalseWhenRecentQuarterMissing(): void
    {
        // Insert 4 old quarters but most recent quarter is missing the metric
        $year = (int) date('Y');
        $this->insertQuarterlyFinancial($year - 1, 1, ['revenue' => 1000000000]);
        $this->insertQuarterlyFinancial($year - 1, 2, ['revenue' => 1000000000]);
        $this->insertQuarterlyFinancial($year - 1, 3, ['revenue' => 1000000000]);
        $this->insertQuarterlyFinancial($year - 1, 4, ['revenue' => 1000000000]);
        // Most recent quarter has null revenue
        $this->insertQuarterlyFinancial($year, 1, ['revenue' => null]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [],
            quarterMetrics: [
                new MetricDefinition('revenue', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        // Should fail because one of the latest 4 quarters (Q1 of current year) has null revenue
        $this->assertFalse($result);
    }

    public function testGetMissingFocalMetricsReturnsAllMissingMetrics(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
                new MetricDefinition('revenue_ttm', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [
                new MetricDefinition('ebitda', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $missing = $this->query->getMissingFocalMetrics($this->companyId, $requirements);

        $this->assertContains('valuation.market_cap', $missing);
        $this->assertContains('ttm.revenue_ttm', $missing);
        $this->assertContains('annual.ebitda', $missing);
    }

    public function testGetMissingFocalMetricsIgnoresNonFocalMetrics(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_ALL),
                new MetricDefinition('fwd_pe', MetricDefinition::UNIT_RATIO, required: false, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $missing = $this->query->getMissingFocalMetrics($this->companyId, $requirements);

        $this->assertEmpty($missing);
    }

    public function testIsFocalReadyReturnsFalseWhenDerivedNetDebtEbitdaInputsMissing(): void
    {
        // net_debt_ebitda requires net_debt and ebitda from annual_financial
        $this->insertAnnualFinancial((int) date('Y'), ['net_debt' => 500000000]); // ebitda missing

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [
                new MetricDefinition('net_debt_ebitda', MetricDefinition::UNIT_RATIO, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertFalse($result);
    }

    public function testIsFocalReadyReturnsTrueWhenDerivedNetDebtEbitdaInputsPresent(): void
    {
        $this->insertAnnualFinancial((int) date('Y'), [
            'net_debt' => 500000000,
            'ebitda' => 100000000,
        ]);

        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [],
            annualFinancialMetrics: [
                new MetricDefinition('net_debt_ebitda', MetricDefinition::UNIT_RATIO, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    public function testIsFocalReadyIgnoresUnknownMetrics(): void
    {
        $requirements = new DataRequirements(
            historyYears: 3,
            quartersToFetch: 4,
            valuationMetrics: [
                new MetricDefinition('unknown_metric', MetricDefinition::UNIT_CURRENCY, required: true, requiredScope: MetricDefinition::SCOPE_FOCAL),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        $result = $this->query->isFocalReady($this->companyId, $requirements);

        $this->assertTrue($result);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertValuationSnapshot(array $values): void
    {
        Yii::$app->db->createCommand()
            ->insert('valuation_snapshot', array_merge([
                'company_id' => $this->companyId,
                'snapshot_date' => date('Y-m-d'),
                'source_adapter' => 'test',
                'currency' => 'USD',
                'collected_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ], $values))
            ->execute();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertTtmFinancial(array $values): void
    {
        Yii::$app->db->createCommand()
            ->insert('ttm_financial', array_merge([
                'company_id' => $this->companyId,
                'as_of_date' => date('Y-m-d'),
                'currency' => 'USD',
                'calculated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ], $values))
            ->execute();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertAnnualFinancial(int $fiscalYear, array $values): void
    {
        Yii::$app->db->createCommand()
            ->insert('annual_financial', array_merge([
                'company_id' => $this->companyId,
                'fiscal_year' => $fiscalYear,
                'period_end_date' => $fiscalYear . '-12-31',
                'is_current' => 1,
                'source_adapter' => 'test',
                'currency' => 'USD',
                'collected_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ], $values))
            ->execute();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertQuarterlyFinancial(int $fiscalYear, int $quarter, array $values): void
    {
        Yii::$app->db->createCommand()
            ->insert('quarterly_financial', array_merge([
                'company_id' => $this->companyId,
                'fiscal_year' => $fiscalYear,
                'fiscal_quarter' => $quarter,
                'period_end_date' => $fiscalYear . '-' . str_pad((string) ($quarter * 3), 2, '0', STR_PAD_LEFT) . '-30',
                'is_current' => 1,
                'source_adapter' => 'test',
                'currency' => 'USD',
                'collected_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ], $values))
            ->execute();
    }
}
