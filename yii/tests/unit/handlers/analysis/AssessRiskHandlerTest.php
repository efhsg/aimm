<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\analysis\RiskThresholds;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\Risk;
use app\handlers\analysis\AssessRiskHandler;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\handlers\analysis\AssessRiskHandler
 */
final class AssessRiskHandlerTest extends Unit
{
    private AssessRiskHandler $handler;
    private RiskThresholds $thresholds;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AssessRiskHandler();
        $this->thresholds = new RiskThresholds();
    }

    public function testScoresLeverageAcceptable(): void
    {
        // Net Debt / EBITDA = 1.5x (< 2.0x = Acceptable)
        $focal = $this->createCompanyWithFinancials(
            netDebt: 30_000_000_000,
            ebitda: 20_000_000_000
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $leverageFactor = $this->findFactor($result->factors, 'leverage');
        $this->assertNotNull($leverageFactor);
        $this->assertEqualsWithDelta(1.5, $leverageFactor->value, 0.01);
        $this->assertEquals(Risk::Acceptable, $leverageFactor->level);
    }

    public function testScoresLeverageElevated(): void
    {
        // Net Debt / EBITDA = 3.0x (>= 2.0x, < 4.0x = Elevated)
        $focal = $this->createCompanyWithFinancials(
            netDebt: 60_000_000_000,
            ebitda: 20_000_000_000
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $leverageFactor = $this->findFactor($result->factors, 'leverage');
        $this->assertEqualsWithDelta(3.0, $leverageFactor->value, 0.01);
        $this->assertEquals(Risk::Elevated, $leverageFactor->level);
    }

    public function testScoresLeverageUnacceptable(): void
    {
        // Net Debt / EBITDA = 5.0x (>= 4.0x = Unacceptable)
        $focal = $this->createCompanyWithFinancials(
            netDebt: 100_000_000_000,
            ebitda: 20_000_000_000
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $leverageFactor = $this->findFactor($result->factors, 'leverage');
        $this->assertEqualsWithDelta(5.0, $leverageFactor->value, 0.01);
        $this->assertEquals(Risk::Unacceptable, $leverageFactor->level);
    }

    public function testScoresLiquidityRatio(): void
    {
        // Cash / Total Debt = 0.25 (> 0.20 = Acceptable)
        $focal = $this->createCompanyWithFinancials(
            cash: 25_000_000_000,
            totalDebt: 100_000_000_000,
            netDebt: 75_000_000_000,
            ebitda: 50_000_000_000
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $liquidityFactor = $this->findFactor($result->factors, 'liquidity');
        $this->assertNotNull($liquidityFactor);
        $this->assertEqualsWithDelta(0.25, $liquidityFactor->value, 0.01);
        $this->assertEquals(Risk::Acceptable, $liquidityFactor->level);
    }

    public function testScoresFcfCoverage(): void
    {
        // FCF / Net Debt = 0.20 (> 0.15 = Acceptable)
        $focal = $this->createCompanyWithFinancials(
            fcf: 10_000_000_000,
            netDebt: 50_000_000_000,
            ebitda: 50_000_000_000
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $fcfFactor = $this->findFactor($result->factors, 'fcf_coverage');
        $this->assertNotNull($fcfFactor);
        $this->assertEqualsWithDelta(0.20, $fcfFactor->value, 0.01);
        $this->assertEquals(Risk::Acceptable, $fcfFactor->level);
    }

    public function testUnacceptableFactorOverridesAll(): void
    {
        // Leverage is unacceptable, other factors are acceptable
        $focal = $this->createCompanyWithFinancials(
            netDebt: 100_000_000_000,
            ebitda: 20_000_000_000, // 5.0x leverage = Unacceptable
            cash: 50_000_000_000,
            totalDebt: 100_000_000_000, // 0.5 liquidity = Acceptable
            fcf: 20_000_000_000 // 0.2 FCF coverage = Acceptable
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $this->assertEquals(Risk::Unacceptable, $result->assessment);
        $this->assertEquals(-1.0, $result->compositeScore);
    }

    public function testCalculatesWeightedComposite(): void
    {
        // All factors acceptable => composite ~1.0
        $focal = $this->createCompanyWithFinancials(
            netDebt: 30_000_000_000,
            ebitda: 20_000_000_000, // 1.5x leverage = Acceptable
            cash: 50_000_000_000,
            totalDebt: 100_000_000_000, // 0.5 liquidity = Acceptable
            fcf: 10_000_000_000 // 0.33 FCF coverage = Acceptable
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        $this->assertEquals(Risk::Acceptable, $result->assessment);
        $this->assertEqualsWithDelta(1.0, $result->compositeScore, 0.01);
    }

    public function testHandlesMissingData(): void
    {
        // No annual data
        $focal = $this->createCompanyWithNoFinancials();

        $result = $this->handler->handle($focal, $this->thresholds);

        $this->assertEquals(Risk::Elevated, $result->assessment);
        $this->assertEquals(0.0, $result->compositeScore);
        $this->assertEmpty($result->factors);
    }

    public function testHandlesPartialData(): void
    {
        // Only EBITDA and net debt available
        $focal = $this->createCompanyWithFinancials(
            netDebt: 30_000_000_000,
            ebitda: 20_000_000_000
            // cash, totalDebt, fcf are null
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        // Should still calculate leverage, others get Elevated default
        $this->assertCount(3, $result->factors);

        $leverageFactor = $this->findFactor($result->factors, 'leverage');
        $this->assertNotNull($leverageFactor->value);
        $this->assertEquals(Risk::Acceptable, $leverageFactor->level);

        $liquidityFactor = $this->findFactor($result->factors, 'liquidity');
        $this->assertNull($liquidityFactor->value);
        $this->assertEquals(Risk::Elevated, $liquidityFactor->level);
    }

    public function testMixedRiskLevels(): void
    {
        // Leverage: Acceptable, Liquidity: Elevated, FCF: Acceptable
        $focal = $this->createCompanyWithFinancials(
            netDebt: 30_000_000_000,
            ebitda: 20_000_000_000, // 1.5x = Acceptable
            cash: 15_000_000_000,
            totalDebt: 100_000_000_000, // 0.15 = Elevated
            fcf: 10_000_000_000 // 0.33 = Acceptable
        );

        $result = $this->handler->handle($focal, $this->thresholds);

        // Weighted: (1.0 * 0.4) + (0.0 * 0.3) + (1.0 * 0.3) = 0.7
        $this->assertEquals(Risk::Acceptable, $result->assessment);
        $this->assertEqualsWithDelta(0.7, $result->compositeScore, 0.01);
    }

    /**
     * @param \app\dto\report\RiskFactor[] $factors
     */
    private function findFactor(array $factors, string $key): ?\app\dto\report\RiskFactor
    {
        foreach ($factors as $factor) {
            if ($factor->key === $key) {
                return $factor;
            }
        }
        return null;
    }

    private function createCompanyWithFinancials(
        ?float $netDebt = null,
        ?float $ebitda = null,
        ?float $cash = null,
        ?float $totalDebt = null,
        ?float $fcf = null
    ): CompanyData {
        $latest = new AnnualFinancials(
            fiscalYear: 2024,
            ebitda: $ebitda !== null ? $this->createMoney($ebitda) : null,
            freeCashFlow: $fcf !== null ? $this->createMoney($fcf) : null,
            totalDebt: $totalDebt !== null ? $this->createMoney($totalDebt) : null,
            cashAndEquivalents: $cash !== null ? $this->createMoney($cash) : null,
            netDebt: $netDebt !== null ? $this->createMoney($netDebt) : null,
        );

        return new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(marketCap: $this->createMoney(3_000_000_000_000)),
            financials: new FinancialsData(historyYears: 1, annualData: [$latest]),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createCompanyWithNoFinancials(): CompanyData
    {
        return new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(marketCap: $this->createMoney(3_000_000_000_000)),
            financials: new FinancialsData(historyYears: 0, annualData: []),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createMoney(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.value', (string) $value),
        );
    }
}
