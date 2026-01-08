<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\RiskThresholds;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\report\RiskBreakdown;
use app\dto\report\RiskFactor;
use app\enums\Risk;

/**
 * Scores company risk based on balance sheet metrics.
 *
 * Factors assessed:
 * - Leverage: Net Debt / EBITDA (lower is better)
 * - Liquidity: Cash / Total Debt (higher is better)
 * - FCF Coverage: FCF / Net Debt (higher is better)
 *
 * Any single factor at "unacceptable" level triggers overall Unacceptable risk.
 */
final class AssessRiskHandler implements AssessRiskInterface
{
    public function handle(
        CompanyData $focal,
        RiskThresholds $thresholds
    ): RiskBreakdown {
        // Get latest year of annual data
        $annualData = $focal->financials->annualData;
        usort($annualData, static fn (AnnualFinancials $a, AnnualFinancials $b): int => $b->fiscalYear <=> $a->fiscalYear);

        if (count($annualData) === 0) {
            return $this->insufficientData();
        }

        $latest = $annualData[0];

        // Calculate each factor
        $factors = [
            $this->assessLeverage($latest, $thresholds),
            $this->assessLiquidity($latest, $thresholds),
            $this->assessFcfCoverage($latest, $thresholds),
        ];

        // Check for any unacceptable factor (immediate fail)
        foreach ($factors as $factor) {
            if ($factor->level === Risk::Unacceptable) {
                return new RiskBreakdown(
                    assessment: Risk::Unacceptable,
                    compositeScore: -1.0,
                    factors: $factors,
                );
            }
        }

        // Calculate weighted score
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($factors as $factor) {
            if ($factor->value !== null) {
                $score = match ($factor->level) {
                    Risk::Acceptable => 1.0,
                    Risk::Elevated => 0.0,
                    Risk::Unacceptable => -1.0,
                };
                $weightedSum += $score * $factor->weight;
                $totalWeight += $factor->weight;
            }
        }

        $compositeScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        // Determine assessment
        $assessment = match (true) {
            $compositeScore >= 0.5 => Risk::Acceptable,
            $compositeScore >= -0.5 => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskBreakdown(
            assessment: $assessment,
            compositeScore: $compositeScore,
            factors: $factors,
        );
    }

    private function assessLeverage(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $netDebt = $latest->netDebt?->getBaseValue();
        $ebitda = $latest->ebitda?->getBaseValue();

        if ($netDebt === null || $ebitda === null || $ebitda == 0) {
            return new RiskFactor(
                key: 'leverage',
                label: 'Net Debt / EBITDA',
                value: null,
                level: Risk::Elevated, // Conservative default
                weight: $thresholds->leverageWeight,
                formula: 'net_debt / ebitda',
            );
        }

        $ratio = $netDebt / $ebitda;

        // Lower is better for leverage
        $level = match (true) {
            $ratio < $thresholds->leverageAcceptable => Risk::Acceptable,
            $ratio < $thresholds->leverageElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'leverage',
            label: 'Net Debt / EBITDA',
            value: $ratio,
            level: $level,
            weight: $thresholds->leverageWeight,
            formula: 'net_debt / ebitda',
        );
    }

    private function assessLiquidity(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $cash = $latest->cashAndEquivalents?->getBaseValue();
        $totalDebt = $latest->totalDebt?->getBaseValue();

        if ($cash === null || $totalDebt === null || $totalDebt == 0) {
            return new RiskFactor(
                key: 'liquidity',
                label: 'Cash / Total Debt',
                value: null,
                level: Risk::Elevated,
                weight: $thresholds->liquidityWeight,
                formula: 'cash_and_equivalents / total_debt',
            );
        }

        $ratio = $cash / $totalDebt;

        // Higher is better for liquidity
        $level = match (true) {
            $ratio > $thresholds->liquidityAcceptable => Risk::Acceptable,
            $ratio > $thresholds->liquidityElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'liquidity',
            label: 'Cash / Total Debt',
            value: $ratio,
            level: $level,
            weight: $thresholds->liquidityWeight,
            formula: 'cash_and_equivalents / total_debt',
        );
    }

    private function assessFcfCoverage(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $fcf = $latest->freeCashFlow?->getBaseValue();
        $netDebt = $latest->netDebt?->getBaseValue();

        if ($fcf === null || $netDebt === null || $netDebt == 0) {
            return new RiskFactor(
                key: 'fcf_coverage',
                label: 'FCF / Net Debt',
                value: null,
                level: Risk::Elevated,
                weight: $thresholds->fcfCoverageWeight,
                formula: 'free_cash_flow / net_debt',
            );
        }

        $ratio = $fcf / $netDebt;

        // Higher is better for FCF coverage
        $level = match (true) {
            $ratio > $thresholds->fcfCoverageAcceptable => Risk::Acceptable,
            $ratio > $thresholds->fcfCoverageElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'fcf_coverage',
            label: 'FCF / Net Debt',
            value: $ratio,
            level: $level,
            weight: $thresholds->fcfCoverageWeight,
            formula: 'free_cash_flow / net_debt',
        );
    }

    private function insufficientData(): RiskBreakdown
    {
        return new RiskBreakdown(
            assessment: Risk::Elevated,
            compositeScore: 0.0,
            factors: [],
        );
    }
}
