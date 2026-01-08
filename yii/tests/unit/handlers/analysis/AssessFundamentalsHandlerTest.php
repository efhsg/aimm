<?php

declare(strict_types=1);

namespace tests\unit\handlers\analysis;

use app\dto\analysis\FundamentalsWeights;
use app\dto\AnnualFinancials;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\Fundamentals;
use app\handlers\analysis\AssessFundamentalsHandler;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\handlers\analysis\AssessFundamentalsHandler
 */
final class AssessFundamentalsHandlerTest extends Unit
{
    private AssessFundamentalsHandler $handler;
    private FundamentalsWeights $weights;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new AssessFundamentalsHandler();
        $this->weights = new FundamentalsWeights();
    }

    public function testScoresRevenueGrowthPositive(): void
    {
        // 25% revenue growth => normalized score +1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 125_000_000_000
        );

        $result = $this->handler->handle($focal, $this->weights);

        $revenueComponent = $this->findComponent($result->components, 'revenue_growth');
        $this->assertNotNull($revenueComponent);
        $this->assertEquals(25.0, $revenueComponent->changePercent);
        $this->assertEquals(1.0, $revenueComponent->normalizedScore);
    }

    public function testScoresRevenueGrowthNegative(): void
    {
        // -25% revenue decline => normalized score -1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 75_000_000_000
        );

        $result = $this->handler->handle($focal, $this->weights);

        $revenueComponent = $this->findComponent($result->components, 'revenue_growth');
        $this->assertNotNull($revenueComponent);
        $this->assertEquals(-25.0, $revenueComponent->changePercent);
        $this->assertEquals(-1.0, $revenueComponent->normalizedScore);
    }

    public function testScoresMarginExpansion(): void
    {
        // Prior margin: 20%, Latest margin: 25% => +5pp => score +1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 100_000_000_000,
            priorEbitda: 20_000_000_000,
            latestEbitda: 25_000_000_000
        );

        $result = $this->handler->handle($focal, $this->weights);

        $marginComponent = $this->findComponent($result->components, 'margin_expansion');
        $this->assertNotNull($marginComponent);
        $this->assertEquals(20.0, $marginComponent->priorValue);
        $this->assertEquals(25.0, $marginComponent->latestValue);
        $this->assertEquals(5.0, $marginComponent->changePercent);
        $this->assertEquals(1.0, $marginComponent->normalizedScore);
    }

    public function testScoresDebtReduction(): void
    {
        // Debt reduced by 25% => score +1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 100_000_000_000,
            priorNetDebt: 50_000_000_000,
            latestNetDebt: 37_500_000_000
        );

        $result = $this->handler->handle($focal, $this->weights);

        $debtComponent = $this->findComponent($result->components, 'debt_reduction');
        $this->assertNotNull($debtComponent);
        $this->assertEquals(25.0, $debtComponent->changePercent);
        $this->assertEquals(1.0, $debtComponent->normalizedScore);
    }

    public function testCalculatesWeightedComposite(): void
    {
        // All metrics at +1.0 => composite = 1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 125_000_000_000, // +25% growth
            priorEbitda: 20_000_000_000,
            latestEbitda: 30_000_000_000, // +5pp margin expansion
            priorFcf: 10_000_000_000,
            latestFcf: 12_500_000_000, // +25% FCF growth
            priorNetDebt: 40_000_000_000,
            latestNetDebt: 30_000_000_000  // 25% debt reduction
        );

        $result = $this->handler->handle($focal, $this->weights);

        $this->assertEqualsWithDelta(1.0, $result->compositeScore, 0.01);
    }

    public function testReturnsImprovingAboveThreshold(): void
    {
        // Composite >= 0.30 => Improving
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 125_000_000_000, // +25% => +1.0
            priorEbitda: 20_000_000_000,
            latestEbitda: 25_000_000_000, // +5pp => +1.0
            priorFcf: 10_000_000_000,
            latestFcf: 12_500_000_000, // +25% => +1.0
            priorNetDebt: 40_000_000_000,
            latestNetDebt: 30_000_000_000  // +25% => +1.0
        );

        $result = $this->handler->handle($focal, $this->weights);

        $this->assertEquals(Fundamentals::Improving, $result->assessment);
    }

    public function testReturnsDeterioratingBelowThreshold(): void
    {
        // Composite <= -0.30 => Deteriorating
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 75_000_000_000, // -25% => -1.0
            priorEbitda: 25_000_000_000,
            latestEbitda: 18_000_000_000, // -7pp => -1.0
            priorFcf: 10_000_000_000,
            latestFcf: 7_500_000_000, // -25% => -1.0
            priorNetDebt: 40_000_000_000,
            latestNetDebt: 50_000_000_000  // +25% increase => -1.0
        );

        $result = $this->handler->handle($focal, $this->weights);

        $this->assertEquals(Fundamentals::Deteriorating, $result->assessment);
    }

    public function testReturnsMixedInMiddle(): void
    {
        // Mixed metrics => Mixed assessment
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 105_000_000_000, // +5% => 0.0
            priorEbitda: 20_000_000_000,
            latestEbitda: 20_000_000_000, // 0pp => 0.0
            priorFcf: 10_000_000_000,
            latestFcf: 10_000_000_000, // 0% => 0.0
            priorNetDebt: 40_000_000_000,
            latestNetDebt: 40_000_000_000  // 0% => 0.0
        );

        $result = $this->handler->handle($focal, $this->weights);

        $this->assertEquals(Fundamentals::Mixed, $result->assessment);
        $this->assertEqualsWithDelta(0.0, $result->compositeScore, 0.01);
    }

    public function testHandlesInsufficientAnnualData(): void
    {
        $focal = $this->createCompanyWithYearsOfData(1);

        $result = $this->handler->handle($focal, $this->weights);

        $this->assertEquals(Fundamentals::Mixed, $result->assessment);
        $this->assertEquals(0.0, $result->compositeScore);
        $this->assertEmpty($result->components);
    }

    public function testHandlesNullValues(): void
    {
        // Only revenue available
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 125_000_000_000
            // Other metrics null
        );

        $result = $this->handler->handle($focal, $this->weights);

        // Should still calculate based on available data
        $this->assertCount(4, $result->components);

        $revenueComponent = $this->findComponent($result->components, 'revenue_growth');
        $this->assertNotNull($revenueComponent->normalizedScore);

        $marginComponent = $this->findComponent($result->components, 'margin_expansion');
        $this->assertNull($marginComponent->normalizedScore);
    }

    public function testCustomWeights(): void
    {
        $weights = new FundamentalsWeights(
            revenueGrowthWeight: 1.0,
            marginExpansionWeight: 0.0,
            fcfTrendWeight: 0.0,
            debtReductionWeight: 0.0,
            improvingThreshold: 0.5,
            deterioratingThreshold: -0.5,
        );

        // Only revenue matters, +25% growth => score 1.0
        $focal = $this->createCompanyWithFinancials(
            priorRevenue: 100_000_000_000,
            latestRevenue: 125_000_000_000
        );

        $result = $this->handler->handle($focal, $weights);

        $this->assertEquals(1.0, $result->compositeScore);
        $this->assertEquals(Fundamentals::Improving, $result->assessment);
    }

    /**
     * @param \app\dto\report\TrendMetric[] $components
     */
    private function findComponent(array $components, string $key): ?\app\dto\report\TrendMetric
    {
        foreach ($components as $component) {
            if ($component->key === $key) {
                return $component;
            }
        }
        return null;
    }

    private function createCompanyWithFinancials(
        ?float $priorRevenue = null,
        ?float $latestRevenue = null,
        ?float $priorEbitda = null,
        ?float $latestEbitda = null,
        ?float $priorFcf = null,
        ?float $latestFcf = null,
        ?float $priorNetDebt = null,
        ?float $latestNetDebt = null
    ): CompanyData {
        $prior = new AnnualFinancials(
            fiscalYear: 2023,
            revenue: $priorRevenue !== null ? $this->createMoney($priorRevenue) : null,
            ebitda: $priorEbitda !== null ? $this->createMoney($priorEbitda) : null,
            freeCashFlow: $priorFcf !== null ? $this->createMoney($priorFcf) : null,
            netDebt: $priorNetDebt !== null ? $this->createMoney($priorNetDebt) : null,
        );

        $latest = new AnnualFinancials(
            fiscalYear: 2024,
            revenue: $latestRevenue !== null ? $this->createMoney($latestRevenue) : null,
            ebitda: $latestEbitda !== null ? $this->createMoney($latestEbitda) : null,
            freeCashFlow: $latestFcf !== null ? $this->createMoney($latestFcf) : null,
            netDebt: $latestNetDebt !== null ? $this->createMoney($latestNetDebt) : null,
        );

        return new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(marketCap: $this->createMoney(3_000_000_000_000)),
            financials: new FinancialsData(historyYears: 2, annualData: [$prior, $latest]),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createCompanyWithYearsOfData(int $years): CompanyData
    {
        $annualData = [];
        for ($i = 0; $i < $years; $i++) {
            $annualData[] = new AnnualFinancials(
                fiscalYear: 2024 - $i,
                revenue: $this->createMoney(100_000_000_000),
            );
        }

        return new CompanyData(
            ticker: 'AAPL',
            name: 'Apple Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(marketCap: $this->createMoney(3_000_000_000_000)),
            financials: new FinancialsData(historyYears: $years, annualData: $annualData),
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
