<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\analysis\IndustryAnalysisContext;
use app\dto\CompanyData;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\factories\CompanyDataDossierFactoryInterface;
use app\factories\DataPointFactory;
use app\queries\CompanyQuery;
use app\queries\IndustryAnalysisQuery;
use app\queries\MacroIndicatorQuery;
use app\queries\PriceHistoryQuery;
use Codeception\Test\Unit;

/**
 * @covers \app\queries\IndustryAnalysisQuery
 */
final class IndustryAnalysisQueryTest extends Unit
{
    private CompanyQuery $companyQuery;
    private CompanyDataDossierFactoryInterface $companyFactory;
    private MacroIndicatorQuery $macroQuery;
    private PriceHistoryQuery $priceHistoryQuery;
    private IndustryAnalysisQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyQuery = $this->createMock(CompanyQuery::class);
        $this->companyFactory = $this->createMock(CompanyDataDossierFactoryInterface::class);
        $this->macroQuery = $this->createMock(MacroIndicatorQuery::class);
        $this->priceHistoryQuery = $this->createMock(PriceHistoryQuery::class);

        $this->query = new IndustryAnalysisQuery(
            $this->companyQuery,
            $this->companyFactory,
            $this->macroQuery,
            $this->priceHistoryQuery,
        );
    }

    public function testReturnsEmptyContextWhenNoCompanies(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([]);

        $result = $this->query->getForAnalysis(1, 'test-industry');

        $this->assertInstanceOf(IndustryAnalysisContext::class, $result);
        $this->assertEmpty($result->companies);
        $this->assertEquals(1, $result->industryId);
        $this->assertEquals('test-industry', $result->industrySlug);
    }

    public function testFiltersOutCompaniesWhereFactoryReturnsNull(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            ['id' => 1, 'ticker' => 'AAPL', 'valuation_collected_at' => '2024-01-15'],
            ['id' => 2, 'ticker' => 'MSFT', 'valuation_collected_at' => '2024-01-15'],
            ['id' => 3, 'ticker' => 'INVALID', 'valuation_collected_at' => '2024-01-15'],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(function (array $row): ?CompanyData {
                if ($row['ticker'] === 'INVALID') {
                    return null; // Factory returns null for invalid company
                }
                return $this->createCompanyData($row['ticker']);
            });

        $result = $this->query->getForAnalysis(1, 'test-industry');

        $this->assertCount(2, $result->companies);
        $this->assertArrayHasKey('AAPL', $result->companies);
        $this->assertArrayHasKey('MSFT', $result->companies);
        $this->assertArrayNotHasKey('INVALID', $result->companies);
    }

    public function testUsesLatestTimestampAcrossCompanies(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            ['id' => 1, 'ticker' => 'AAPL', 'valuation_collected_at' => '2024-01-10T10:00:00Z'],
            ['id' => 2, 'ticker' => 'MSFT', 'valuation_collected_at' => '2024-01-15T10:00:00Z'], // Latest
            ['id' => 3, 'ticker' => 'GOOGL', 'valuation_collected_at' => '2024-01-12T10:00:00Z'],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(fn (array $row) => $this->createCompanyData($row['ticker']));

        $result = $this->query->getForAnalysis(1, 'test-industry');

        $this->assertEquals('2024-01-15', $result->collectedAt->format('Y-m-d'));
    }

    public function testUsesLatestFromMultipleTimestampFields(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            [
                'id' => 1,
                'ticker' => 'AAPL',
                'valuation_collected_at' => '2024-01-10T10:00:00Z',
                'financials_collected_at' => '2024-01-20T10:00:00Z', // Latest
                'quarters_collected_at' => '2024-01-15T10:00:00Z',
            ],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(fn (array $row) => $this->createCompanyData($row['ticker']));

        $result = $this->query->getForAnalysis(1, 'test-industry');

        $this->assertEquals('2024-01-20', $result->collectedAt->format('Y-m-d'));
    }

    public function testHandlesMissingPolicyGracefully(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            ['id' => 1, 'ticker' => 'AAPL', 'valuation_collected_at' => '2024-01-15'],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(fn (array $row) => $this->createCompanyData($row['ticker']));

        $result = $this->query->getForAnalysis(1, 'test-industry', null);

        $this->assertInstanceOf(IndustryAnalysisContext::class, $result);
        $this->assertCount(1, $result->companies);
    }

    public function testIncludesMacroTimestampWhenPolicyProvided(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            ['id' => 1, 'ticker' => 'AAPL', 'valuation_collected_at' => '2024-01-10T10:00:00Z'],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(fn (array $row) => $this->createCompanyData($row['ticker']));

        // Macro data is more recent
        $this->priceHistoryQuery->method('findLatestBySymbol')->willReturn([
            'collected_at' => '2024-01-20T10:00:00Z',
        ]);

        $result = $this->query->getForAnalysis(1, 'test-industry', [
            'commodity_benchmark' => 'CL=F',
        ]);

        $this->assertEquals('2024-01-20', $result->collectedAt->format('Y-m-d'));
    }

    public function testGetTickersReturnsAllCompanyTickers(): void
    {
        $this->companyQuery->method('findByIndustry')->willReturn([
            ['id' => 1, 'ticker' => 'AAPL', 'valuation_collected_at' => '2024-01-15'],
            ['id' => 2, 'ticker' => 'MSFT', 'valuation_collected_at' => '2024-01-15'],
            ['id' => 3, 'ticker' => 'GOOGL', 'valuation_collected_at' => '2024-01-15'],
        ]);

        $this->companyFactory->method('createFromDossier')
            ->willReturnCallback(fn (array $row) => $this->createCompanyData($row['ticker']));

        $result = $this->query->getForAnalysis(1, 'test-industry');

        $tickers = $result->getTickers();
        $this->assertCount(3, $tickers);
        $this->assertContains('AAPL', $tickers);
        $this->assertContains('MSFT', $tickers);
        $this->assertContains('GOOGL', $tickers);
    }

    private function createCompanyData(string $ticker): CompanyData
    {
        $dataPointFactory = new DataPointFactory();

        return new CompanyData(
            ticker: $ticker,
            name: $ticker . ' Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $dataPointFactory->fromCache(
                    unit: 'currency',
                    value: 3000000000000,
                    originalAsOf: new \DateTimeImmutable(),
                    cacheSource: 'dossier',
                    cacheAgeDays: 0,
                    currency: 'USD',
                ),
            ),
            financials: new FinancialsData(historyYears: 2, annualData: []),
            quarters: new QuartersData(quarters: []),
        );
    }
}
