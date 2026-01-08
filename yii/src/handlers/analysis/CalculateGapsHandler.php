<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\CompanyData;
use app\dto\report\MetricGap;
use app\dto\report\PeerAverages;
use app\dto\report\ValuationGapSummary;
use app\enums\GapDirection;

/**
 * Calculates valuation gaps between focal company and peer averages.
 *
 * Gap interpretation:
 * - Lower-is-better metrics (P/E, EV/EBITDA): positive gap = undervalued
 * - Higher-is-better metrics (yields): positive gap = undervalued
 */
final class CalculateGapsHandler implements CalculateGapsInterface
{
    public function handle(
        CompanyData $focal,
        PeerAverages $peerAverages,
        AnalysisThresholds $thresholds
    ): ValuationGapSummary {
        $gaps = [];

        // Forward P/E (lower is better)
        $gaps[] = $this->calculateGap(
            'fwd_pe',
            'Forward P/E',
            $focal->valuation->fwdPe?->value,
            $peerAverages->fwdPe,
            true,
            $thresholds->fairValueThreshold
        );

        // EV/EBITDA (lower is better)
        $gaps[] = $this->calculateGap(
            'ev_ebitda',
            'EV/EBITDA',
            $focal->valuation->evEbitda?->value,
            $peerAverages->evEbitda,
            true,
            $thresholds->fairValueThreshold
        );

        // FCF Yield (higher is better)
        $gaps[] = $this->calculateGap(
            'fcf_yield',
            'FCF Yield',
            $focal->valuation->fcfYield?->value,
            $peerAverages->fcfYieldPercent,
            false,
            $thresholds->fairValueThreshold
        );

        // Dividend Yield (higher is better)
        $gaps[] = $this->calculateGap(
            'div_yield',
            'Dividend Yield',
            $focal->valuation->divYield?->value,
            $peerAverages->divYieldPercent,
            false,
            $thresholds->fairValueThreshold
        );

        // Filter to valid gaps only
        $validGaps = array_filter($gaps, static fn (MetricGap $g): bool => $g->gapPercent !== null);
        $gapValues = array_map(static fn (MetricGap $g): float => $g->gapPercent, $validGaps);

        // Calculate composite
        $compositeGap = null;
        $direction = null;

        if (count($gapValues) >= $thresholds->minMetricsForGap) {
            $compositeGap = array_sum($gapValues) / count($gapValues);
            $direction = $this->determineDirection($compositeGap, $thresholds->fairValueThreshold);
        }

        return new ValuationGapSummary(
            compositeGap: $compositeGap,
            direction: $direction,
            individualGaps: $gaps,
            metricsUsed: count($gapValues),
        );
    }

    private function calculateGap(
        string $key,
        string $label,
        ?float $focalValue,
        ?float $peerAverage,
        bool $lowerIsBetter,
        float $fairValueThreshold
    ): MetricGap {
        if ($focalValue === null || $peerAverage === null || $peerAverage == 0) {
            return new MetricGap(
                key: $key,
                label: $label,
                focalValue: $focalValue,
                peerAverage: $peerAverage,
                gapPercent: null,
                direction: null,
                interpretation: $lowerIsBetter ? 'lower_better' : 'higher_better',
            );
        }

        // Calculate gap percentage
        // For lower_better: gap = (peer - focal) / peer * 100
        //   Positive gap = undervalued (focal is cheaper)
        // For higher_better: gap = (focal - peer) / peer * 100
        //   Positive gap = undervalued (focal has better yield)
        $gap = $lowerIsBetter
            ? (($peerAverage - $focalValue) / $peerAverage) * 100
            : (($focalValue - $peerAverage) / $peerAverage) * 100;

        $direction = $this->determineDirection($gap, $fairValueThreshold);

        return new MetricGap(
            key: $key,
            label: $label,
            focalValue: $focalValue,
            peerAverage: $peerAverage,
            gapPercent: $gap,
            direction: $direction,
            interpretation: $lowerIsBetter ? 'lower_better' : 'higher_better',
        );
    }

    private function determineDirection(float $gap, float $threshold): GapDirection
    {
        return match (true) {
            $gap > $threshold => GapDirection::Undervalued,
            $gap < -$threshold => GapDirection::Overvalued,
            default => GapDirection::Fair,
        };
    }
}
