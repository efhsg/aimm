<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\enums\Fundamentals;
use app\enums\GapDirection;
use app\enums\Rating;
use app\enums\RatingRulePath;
use app\enums\Risk;
use app\handlers\analysis\DetermineRatingHandler;
use Codeception\Test\Unit;

/**
 * @covers \app\handlers\analysis\DetermineRatingHandler
 */
final class DetermineRatingHandlerTest extends Unit
{
    private DetermineRatingHandler $handler;
    private AnalysisThresholds $thresholds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new DetermineRatingHandler();
        $this->thresholds = new AnalysisThresholds();
    }

    public function testSellOnDeterioratingFundamentals(): void
    {
        $fundamentals = $this->createFundamentals(Fundamentals::Deteriorating);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(20.0);

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Sell, $result->rating);
        $this->assertEquals(RatingRulePath::SellFundamentals, $result->rulePath);
    }

    public function testSellOnUnacceptableRisk(): void
    {
        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Unacceptable);
        $valuationGap = $this->createValuationGap(20.0);

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Sell, $result->rating);
        $this->assertEquals(RatingRulePath::SellRisk, $result->rulePath);
    }

    public function testHoldOnInsufficientData(): void
    {
        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(null); // No composite gap

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Hold, $result->rating);
        $this->assertEquals(RatingRulePath::HoldInsufficientData, $result->rulePath);
    }

    public function testBuyWhenAllConditionsMet(): void
    {
        // buyGapThreshold = 15.0 by default
        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(20.0); // > 15%

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Buy, $result->rating);
        $this->assertEquals(RatingRulePath::BuyAllConditions, $result->rulePath);
    }

    public function testHoldAsDefault(): void
    {
        // Gap is positive but below threshold
        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(10.0); // Below 15% threshold

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Hold, $result->rating);
        $this->assertEquals(RatingRulePath::HoldDefault, $result->rulePath);
    }

    public function testSellFundamentalsTakesPrecedenceOverRisk(): void
    {
        // Both conditions for SELL are met, but fundamentals comes first
        $fundamentals = $this->createFundamentals(Fundamentals::Deteriorating);
        $risk = $this->createRisk(Risk::Unacceptable);
        $valuationGap = $this->createValuationGap(20.0);

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Sell, $result->rating);
        $this->assertEquals(RatingRulePath::SellFundamentals, $result->rulePath);
    }

    public function testHoldWhenFundamentalsMixed(): void
    {
        // Even with good gap and acceptable risk, mixed fundamentals prevents BUY
        $fundamentals = $this->createFundamentals(Fundamentals::Mixed);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(20.0);

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Hold, $result->rating);
        $this->assertEquals(RatingRulePath::HoldDefault, $result->rulePath);
    }

    public function testHoldWhenRiskElevated(): void
    {
        // Even with good gap and improving fundamentals, elevated risk prevents BUY
        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Elevated);
        $valuationGap = $this->createValuationGap(20.0);

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $this->thresholds);

        $this->assertEquals(Rating::Hold, $result->rating);
        $this->assertEquals(RatingRulePath::HoldDefault, $result->rulePath);
    }

    public function testCustomBuyGapThreshold(): void
    {
        $thresholds = new AnalysisThresholds(buyGapThreshold: 25.0);

        $fundamentals = $this->createFundamentals(Fundamentals::Improving);
        $risk = $this->createRisk(Risk::Acceptable);
        $valuationGap = $this->createValuationGap(20.0); // Below 25% threshold

        $result = $this->handler->handle($fundamentals, $risk, $valuationGap, $thresholds);

        $this->assertEquals(Rating::Hold, $result->rating);
        $this->assertEquals(RatingRulePath::HoldDefault, $result->rulePath);
    }

    private function createFundamentals(Fundamentals $assessment): FundamentalsBreakdown
    {
        return new FundamentalsBreakdown(
            assessment: $assessment,
            compositeScore: match ($assessment) {
                Fundamentals::Improving => 0.5,
                Fundamentals::Mixed => 0.0,
                Fundamentals::Deteriorating => -0.5,
            },
            components: [],
        );
    }

    private function createRisk(Risk $assessment): RiskBreakdown
    {
        return new RiskBreakdown(
            assessment: $assessment,
            compositeScore: match ($assessment) {
                Risk::Acceptable => 1.0,
                Risk::Elevated => 0.0,
                Risk::Unacceptable => -1.0,
            },
            factors: [],
        );
    }

    private function createValuationGap(?float $compositeGap): ValuationGapSummary
    {
        return new ValuationGapSummary(
            compositeGap: $compositeGap,
            direction: $compositeGap !== null ? GapDirection::Undervalued : null,
            individualGaps: [],
            metricsUsed: $compositeGap !== null ? 3 : 0,
        );
    }
}
