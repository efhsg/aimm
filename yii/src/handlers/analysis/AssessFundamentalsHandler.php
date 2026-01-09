<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\FundamentalsWeights;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\TrendMetric;
use app\enums\Fundamentals;

/**
 * Scores company fundamentals based on YoY trends.
 *
 * Components scored:
 * - Revenue Growth: YoY change in revenue
 * - Margin Expansion: Change in EBITDA margin (percentage points)
 * - FCF Trend: YoY change in free cash flow
 * - Debt Reduction: YoY reduction in net debt
 *
 * Each component is normalized to [-1, +1] and weighted.
 */
final class AssessFundamentalsHandler implements AssessFundamentalsInterface
{
    public function handle(
        CompanyData $company,
        FundamentalsWeights $weights
    ): FundamentalsBreakdown {
        // Get latest 2 years of annual data
        $annualData = $company->financials->annualData;
        usort($annualData, static fn (AnnualFinancials $a, AnnualFinancials $b): int => $b->fiscalYear <=> $a->fiscalYear);

        if (count($annualData) < 2) {
            return $this->insufficientData();
        }

        $latest = $annualData[0];
        $prior = $annualData[1];

        // Calculate each component
        $components = [
            $this->calculateRevenueGrowth($prior, $latest, $weights->revenueGrowthWeight),
            $this->calculateMarginExpansion($prior, $latest, $weights->marginExpansionWeight),
            $this->calculateFcfTrend($prior, $latest, $weights->fcfTrendWeight),
            $this->calculateDebtReduction($prior, $latest, $weights->debtReductionWeight),
        ];

        // Calculate composite score (weighted average of available scores)
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($components as $component) {
            if ($component->normalizedScore !== null) {
                $weightedSum += $component->normalizedScore * $component->weight;
                $totalWeight += $component->weight;
            }
        }

        $compositeScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        // Determine assessment
        $assessment = match (true) {
            $compositeScore >= $weights->improvingThreshold => Fundamentals::Improving,
            $compositeScore <= $weights->deterioratingThreshold => Fundamentals::Deteriorating,
            default => Fundamentals::Mixed,
        };

        return new FundamentalsBreakdown(
            assessment: $assessment,
            compositeScore: $compositeScore,
            components: $components,
        );
    }

    private function calculateRevenueGrowth(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorValue = $prior->revenue?->getBaseValue();
        $latestValue = $latest->revenue?->getBaseValue();

        return $this->buildGrowthMetric(
            'revenue_growth',
            'Revenue Growth',
            $priorValue,
            $latestValue,
            $weight
        );
    }

    private function calculateMarginExpansion(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorMargin = $this->calculateEbitdaMargin($prior);
        $latestMargin = $this->calculateEbitdaMargin($latest);

        if ($priorMargin === null || $latestMargin === null) {
            return new TrendMetric(
                key: 'margin_expansion',
                label: 'EBITDA Margin',
                priorValue: $priorMargin,
                latestValue: $latestMargin,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        // Margin change is in percentage points, not percent change
        $change = $latestMargin - $priorMargin;
        $normalizedScore = $this->normalizeMarginChange($change);

        return new TrendMetric(
            key: 'margin_expansion',
            label: 'EBITDA Margin',
            priorValue: $priorMargin,
            latestValue: $latestMargin,
            changePercent: $change,
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    private function calculateFcfTrend(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorValue = $prior->freeCashFlow?->getBaseValue();
        $latestValue = $latest->freeCashFlow?->getBaseValue();

        return $this->buildGrowthMetric(
            'fcf_trend',
            'Free Cash Flow',
            $priorValue,
            $latestValue,
            $weight
        );
    }

    private function calculateDebtReduction(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorDebt = $prior->netDebt?->getBaseValue();
        $latestDebt = $latest->netDebt?->getBaseValue();

        if ($priorDebt === null || $latestDebt === null || $priorDebt == 0) {
            return new TrendMetric(
                key: 'debt_reduction',
                label: 'Net Debt Reduction',
                priorValue: $priorDebt,
                latestValue: $latestDebt,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        // Debt reduction: positive change = good (debt went down)
        $changePercent = (($priorDebt - $latestDebt) / abs($priorDebt)) * 100;
        $normalizedScore = $this->normalizeGrowthChange($changePercent);

        return new TrendMetric(
            key: 'debt_reduction',
            label: 'Net Debt Reduction',
            priorValue: $priorDebt,
            latestValue: $latestDebt,
            changePercent: $changePercent,
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    private function buildGrowthMetric(
        string $key,
        string $label,
        ?float $priorValue,
        ?float $latestValue,
        float $weight
    ): TrendMetric {
        if ($priorValue === null || $latestValue === null || $priorValue == 0) {
            return new TrendMetric(
                key: $key,
                label: $label,
                priorValue: $priorValue,
                latestValue: $latestValue,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        $changePercent = (($latestValue - $priorValue) / abs($priorValue)) * 100;
        $normalizedScore = $this->normalizeGrowthChange($changePercent);

        return new TrendMetric(
            key: $key,
            label: $label,
            priorValue: $priorValue,
            latestValue: $latestValue,
            changePercent: $changePercent,
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    /**
     * Normalize growth percentage to [-1, +1].
     */
    private function normalizeGrowthChange(float $changePercent): float
    {
        return match (true) {
            $changePercent > 20 => 1.0,
            $changePercent > 10 => 0.5,
            $changePercent > -10 => 0.0,
            $changePercent > -20 => -0.5,
            default => -1.0,
        };
    }

    /**
     * Normalize margin change (percentage points) to [-1, +1].
     */
    private function normalizeMarginChange(float $changePp): float
    {
        return match (true) {
            $changePp > 3 => 1.0,
            $changePp > 1 => 0.5,
            $changePp > -1 => 0.0,
            $changePp > -3 => -0.5,
            default => -1.0,
        };
    }

    private function calculateEbitdaMargin(AnnualFinancials $annual): ?float
    {
        $revenue = $annual->revenue?->getBaseValue();
        $ebitda = $annual->ebitda?->getBaseValue();

        if ($revenue === null || $ebitda === null || $revenue == 0) {
            return null;
        }

        return ($ebitda / $revenue) * 100;
    }

    private function insufficientData(): FundamentalsBreakdown
    {
        return new FundamentalsBreakdown(
            assessment: Fundamentals::Mixed,
            compositeScore: 0.0,
            components: [],
        );
    }
}
