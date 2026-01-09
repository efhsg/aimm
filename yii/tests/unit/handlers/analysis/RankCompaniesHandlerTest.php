<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\report\CompanyAnalysis;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\dto\report\ValuationSnapshot;
use app\enums\Fundamentals;
use app\enums\GapDirection;
use app\enums\Rating;
use app\enums\RatingRulePath;
use app\enums\Risk;
use app\handlers\analysis\RankCompaniesHandler;
use Codeception\Test\Unit;

/**
 * @covers \app\handlers\analysis\RankCompaniesHandler
 */
final class RankCompaniesHandlerTest extends Unit
{
    private RankCompaniesHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new RankCompaniesHandler();
    }

    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->handler->handle([]);

        $this->assertEmpty($result);
    }

    public function testSingleCompanyGetsRankOne(): void
    {
        $company = $this->createCompanyAnalysis('AAPL', Rating::Buy, 0.75);

        $result = $this->handler->handle([$company]);

        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('AAPL', $result[0]->ticker);
    }

    public function testBuyRanksBeforeHold(): void
    {
        $holdCompany = $this->createCompanyAnalysis('HOLD1', Rating::Hold, 0.80);
        $buyCompany = $this->createCompanyAnalysis('BUY1', Rating::Buy, 0.50);

        $result = $this->handler->handle([$holdCompany, $buyCompany]);

        $this->assertCount(2, $result);
        $this->assertEquals('BUY1', $result[0]->ticker);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('HOLD1', $result[1]->ticker);
        $this->assertEquals(2, $result[1]->rank);
    }

    public function testHoldRanksBeforeSell(): void
    {
        $sellCompany = $this->createCompanyAnalysis('SELL1', Rating::Sell, 0.80);
        $holdCompany = $this->createCompanyAnalysis('HOLD1', Rating::Hold, 0.50);

        $result = $this->handler->handle([$sellCompany, $holdCompany]);

        $this->assertCount(2, $result);
        $this->assertEquals('HOLD1', $result[0]->ticker);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('SELL1', $result[1]->ticker);
        $this->assertEquals(2, $result[1]->rank);
    }

    public function testBuyRanksBeforeSell(): void
    {
        $sellCompany = $this->createCompanyAnalysis('SELL1', Rating::Sell, 0.80);
        $buyCompany = $this->createCompanyAnalysis('BUY1', Rating::Buy, 0.30);

        $result = $this->handler->handle([$sellCompany, $buyCompany]);

        $this->assertCount(2, $result);
        $this->assertEquals('BUY1', $result[0]->ticker);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('SELL1', $result[1]->ticker);
        $this->assertEquals(2, $result[1]->rank);
    }

    public function testSameRatingSortsByFundamentalsScoreDescending(): void
    {
        $lowScore = $this->createCompanyAnalysis('LOW', Rating::Buy, 0.50);
        $highScore = $this->createCompanyAnalysis('HIGH', Rating::Buy, 0.90);
        $midScore = $this->createCompanyAnalysis('MID', Rating::Buy, 0.70);

        $result = $this->handler->handle([$lowScore, $highScore, $midScore]);

        $this->assertCount(3, $result);
        $this->assertEquals('HIGH', $result[0]->ticker);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('MID', $result[1]->ticker);
        $this->assertEquals(2, $result[1]->rank);
        $this->assertEquals('LOW', $result[2]->ticker);
        $this->assertEquals(3, $result[2]->rank);
    }

    public function testFullRankingScenario(): void
    {
        $companies = [
            $this->createCompanyAnalysis('SELL_HIGH', Rating::Sell, 0.90),
            $this->createCompanyAnalysis('BUY_LOW', Rating::Buy, 0.40),
            $this->createCompanyAnalysis('HOLD_MID', Rating::Hold, 0.60),
            $this->createCompanyAnalysis('BUY_HIGH', Rating::Buy, 0.80),
            $this->createCompanyAnalysis('HOLD_LOW', Rating::Hold, 0.30),
        ];

        $result = $this->handler->handle($companies);

        $this->assertCount(5, $result);

        // BUYs first (sorted by fundamentals)
        $this->assertEquals('BUY_HIGH', $result[0]->ticker);
        $this->assertEquals(1, $result[0]->rank);
        $this->assertEquals('BUY_LOW', $result[1]->ticker);
        $this->assertEquals(2, $result[1]->rank);

        // HOLDs next (sorted by fundamentals)
        $this->assertEquals('HOLD_MID', $result[2]->ticker);
        $this->assertEquals(3, $result[2]->rank);
        $this->assertEquals('HOLD_LOW', $result[3]->ticker);
        $this->assertEquals(4, $result[3]->rank);

        // SELLs last
        $this->assertEquals('SELL_HIGH', $result[4]->ticker);
        $this->assertEquals(5, $result[4]->rank);
    }

    private function createCompanyAnalysis(string $ticker, Rating $rating, float $fundamentalsScore): CompanyAnalysis
    {
        return new CompanyAnalysis(
            ticker: $ticker,
            name: $ticker . ' Inc.',
            rating: $rating,
            rulePath: RatingRulePath::BuyAllConditions,
            valuation: new ValuationSnapshot(
                marketCapBillions: 100.0,
                fwdPe: 20.0,
                trailingPe: 22.0,
                evEbitda: 12.0,
                fcfYieldPercent: 4.0,
                divYieldPercent: 2.0,
                priceToBook: 3.0,
            ),
            valuationGap: new ValuationGapSummary(
                compositeGap: 5.0,
                direction: GapDirection::Undervalued,
                individualGaps: [],
                metricsUsed: 4,
            ),
            fundamentals: new FundamentalsBreakdown(
                assessment: Fundamentals::Improving,
                compositeScore: $fundamentalsScore,
                components: [],
            ),
            risk: new RiskBreakdown(
                assessment: Risk::Acceptable,
                compositeScore: 0.8,
                factors: [],
            ),
            rank: 0,
        );
    }
}
