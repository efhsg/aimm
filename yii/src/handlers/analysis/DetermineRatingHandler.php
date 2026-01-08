<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\RatingDeterminationResult;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\enums\Fundamentals;
use app\enums\Rating;
use app\enums\RatingRulePath;
use app\enums\Risk;

/**
 * Applies rating decision tree to produce final recommendation.
 *
 * Decision rules (in order):
 * 1. Deteriorating fundamentals → SELL
 * 2. Unacceptable risk → SELL
 * 3. Insufficient valuation data → HOLD
 * 4. All conditions met (undervalued, improving, acceptable risk) → BUY
 * 5. Default → HOLD
 */
final class DetermineRatingHandler implements DetermineRatingInterface
{
    public function handle(
        FundamentalsBreakdown $fundamentals,
        RiskBreakdown $risk,
        ValuationGapSummary $valuationGap,
        AnalysisThresholds $thresholds
    ): RatingDeterminationResult {
        // Rule 1: Deteriorating fundamentals → SELL
        if ($fundamentals->assessment === Fundamentals::Deteriorating) {
            return new RatingDeterminationResult(
                rating: Rating::Sell,
                rulePath: RatingRulePath::SellFundamentals,
            );
        }

        // Rule 2: Unacceptable risk → SELL
        if ($risk->assessment === Risk::Unacceptable) {
            return new RatingDeterminationResult(
                rating: Rating::Sell,
                rulePath: RatingRulePath::SellRisk,
            );
        }

        // Rule 3: Insufficient valuation data → HOLD
        if ($valuationGap->compositeGap === null) {
            return new RatingDeterminationResult(
                rating: Rating::Hold,
                rulePath: RatingRulePath::HoldInsufficientData,
            );
        }

        // Rule 4: All conditions met → BUY
        if (
            $valuationGap->compositeGap > $thresholds->buyGapThreshold
            && $fundamentals->assessment === Fundamentals::Improving
            && $risk->assessment === Risk::Acceptable
        ) {
            return new RatingDeterminationResult(
                rating: Rating::Buy,
                rulePath: RatingRulePath::BuyAllConditions,
            );
        }

        // Default: HOLD
        return new RatingDeterminationResult(
            rating: Rating::Hold,
            rulePath: RatingRulePath::HoldDefault,
        );
    }
}
