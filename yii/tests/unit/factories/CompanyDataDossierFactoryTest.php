<?php

declare(strict_types=1);

namespace tests\unit\factories;

use app\dto\CompanyData;
use app\factories\CompanyDataDossierFactory;
use app\factories\DataPointFactory;
use app\queries\AnnualFinancialQuery;
use app\queries\CompanyQuery;
use app\queries\QuarterlyFinancialQuery;
use app\queries\TtmFinancialQuery;
use app\queries\ValuationSnapshotQuery;
use Codeception\Test\Unit;

/**
 * @covers \app\factories\CompanyDataDossierFactory
 */
final class CompanyDataDossierFactoryTest extends Unit
{
    private CompanyQuery $companyQuery;
    private ValuationSnapshotQuery $valuationQuery;
    private AnnualFinancialQuery $annualQuery;
    private QuarterlyFinancialQuery $quarterlyQuery;
    private TtmFinancialQuery $ttmQuery;
    private DataPointFactory $dataPointFactory;
    private CompanyDataDossierFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyQuery = $this->createMock(CompanyQuery::class);
        $this->valuationQuery = $this->createMock(ValuationSnapshotQuery::class);
        $this->annualQuery = $this->createMock(AnnualFinancialQuery::class);
        $this->quarterlyQuery = $this->createMock(QuarterlyFinancialQuery::class);
        $this->ttmQuery = $this->createMock(TtmFinancialQuery::class);
        $this->dataPointFactory = new DataPointFactory();

        $this->factory = new CompanyDataDossierFactory(
            $this->companyQuery,
            $this->valuationQuery,
            $this->annualQuery,
            $this->quarterlyQuery,
            $this->ttmQuery,
            $this->dataPointFactory,
        );
    }

    public function testReturnsNullWhenCompanyNotFound(): void
    {
        $this->companyQuery->method('findById')->willReturn(null);

        $result = $this->factory->createFromDossier([
            'id' => 999,
            'ticker' => 'UNKNOWN',
        ]);

        $this->assertNull($result);
    }

    public function testReturnsNullWhenMarketCapIsNull(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => null, // No market cap
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([]);
        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
        ]);

        $this->assertNull($result);
    }

    public function testBuildsCompanyDataWithValidValuation(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => 3000000000000,
            'forward_pe' => 25.5,
            'ev_to_ebitda' => 18.2,
            'fcf_yield' => 3.5,
            'dividend_yield' => 0.5,
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->ttmQuery->method('findByCompanyAndDate')->willReturn(null);
        $this->quarterlyQuery->method('findLastFourQuarters')->willReturn([]);
        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([]);
        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
        ]);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertEquals('AAPL', $result->ticker);
        $this->assertEquals('Apple Inc', $result->name);
        $this->assertEquals('NASDAQ', $result->listingExchange);
        $this->assertEquals('USD', $result->listingCurrency);
        $this->assertEquals(3000000000000, $result->valuation->marketCap->value);
        $this->assertEquals(25.5, $result->valuation->fwdPe->value);
        $this->assertEquals(18.2, $result->valuation->evEbitda->value);
    }

    public function testBuildsAnnualFinancialsFromDossier(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => 3000000000000,
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->ttmQuery->method('findByCompanyAndDate')->willReturn(null);
        $this->quarterlyQuery->method('findLastFourQuarters')->willReturn([]);

        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([
            [
                'fiscal_year' => 2024,
                'period_end_date' => '2024-09-30',
                'revenue' => 400000000000,
                'ebitda' => 120000000000,
                'net_income' => 100000000000,
                'currency' => 'USD',
                'collected_at' => '2024-11-01T10:00:00Z',
            ],
            [
                'fiscal_year' => 2023,
                'period_end_date' => '2023-09-30',
                'revenue' => 380000000000,
                'ebitda' => 110000000000,
                'net_income' => 95000000000,
                'currency' => 'USD',
                'collected_at' => '2023-11-01T10:00:00Z',
            ],
        ]);

        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
        ]);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertCount(2, $result->financials->annualData);
        $this->assertEquals(400000000000, $result->financials->annualData[2024]->revenue->value);
        $this->assertEquals(380000000000, $result->financials->annualData[2023]->revenue->value);
    }

    public function testLoadsTtmFreeCashFlowFromTtmTable(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => 3000000000000,
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->ttmQuery->method('findByCompanyAndDate')->willReturn([
            'free_cash_flow' => 100000000000,
            'currency' => 'USD',
            'calculated_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([]);
        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
        ]);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertNotNull($result->valuation->freeCashFlowTtm);
        $this->assertEquals(100000000000, $result->valuation->freeCashFlowTtm->value);
    }

    public function testFallsBackToQuarterSumForTtmFreeCashFlow(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => 3000000000000,
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->ttmQuery->method('findByCompanyAndDate')->willReturn(null);

        $this->quarterlyQuery->method('findLastFourQuarters')->willReturn([
            ['free_cash_flow' => 25000000000, 'collected_at' => '2024-01-15'],
            ['free_cash_flow' => 26000000000, 'collected_at' => '2023-10-15'],
            ['free_cash_flow' => 24000000000, 'collected_at' => '2023-07-15'],
            ['free_cash_flow' => 25000000000, 'collected_at' => '2023-04-15'],
        ]);

        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([]);
        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            'name' => 'Apple Inc',
        ]);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertNotNull($result->valuation->freeCashFlowTtm);
        // Sum of 4 quarters: 25B + 26B + 24B + 25B = 100B
        $this->assertEquals(100000000000, $result->valuation->freeCashFlowTtm->value);
    }

    public function testUsesTickerAsNameWhenNameMissing(): void
    {
        $this->companyQuery->method('findById')->willReturn([
            'id' => 1,
            'ticker' => 'AAPL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
        ]);

        $this->valuationQuery->method('findLatestByCompany')->willReturn([
            'market_cap' => 3000000000000,
            'collected_at' => '2024-01-15T10:00:00Z',
        ]);

        $this->ttmQuery->method('findByCompanyAndDate')->willReturn(null);
        $this->quarterlyQuery->method('findLastFourQuarters')->willReturn([]);
        $this->annualQuery->method('findAllCurrentByCompany')->willReturn([]);
        $this->quarterlyQuery->method('findAllCurrentByCompany')->willReturn([]);

        $result = $this->factory->createFromDossier([
            'id' => 1,
            'ticker' => 'AAPL',
            // 'name' => null - intentionally missing
        ]);

        $this->assertInstanceOf(CompanyData::class, $result);
        $this->assertEquals('AAPL', $result->name);
    }
}
