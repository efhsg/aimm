<?php

declare(strict_types=1);

namespace tests\unit\transformers;

use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\transformers\PeerAverageTransformer;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\transformers\PeerAverageTransformer
 */
final class PeerAverageTransformerTest extends Unit
{
    private PeerAverageTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new PeerAverageTransformer();
    }

    public function testCalculatesAverageForAllCompanies(): void
    {
        $companies = [
            'AAPL' => $this->createCompany('AAPL', 3.0, 25.0, 18.0, 3.5, 0.5),
            'MSFT' => $this->createCompany('MSFT', 2.8, 30.0, 20.0, 4.0, 0.8),
            'GOOGL' => $this->createCompany('GOOGL', 1.8, 22.0, 16.0, 2.5, 0.0),
        ];

        $result = $this->transformer->transform($companies);

        // Averages should include ALL companies
        $this->assertEquals(3, $result->companiesIncluded);
        $this->assertEqualsWithDelta((25.0 + 30.0 + 22.0) / 3, $result->fwdPe, 0.01);
        $this->assertEqualsWithDelta((18.0 + 20.0 + 16.0) / 3, $result->evEbitda, 0.01);
        $this->assertEqualsWithDelta((3.5 + 4.0 + 2.5) / 3, $result->fcfYieldPercent, 0.01);
        $this->assertEqualsWithDelta((0.5 + 0.8 + 0.0) / 3, $result->divYieldPercent, 0.01);
        $this->assertEqualsWithDelta((3.0 + 2.8 + 1.8) / 3, $result->marketCapBillions, 0.01);
    }

    public function testIncludesAllCompaniesInAverage(): void
    {
        $companies = [
            'AAPL' => $this->createCompany('AAPL', 3.0, 100.0, 100.0, 100.0, 100.0),
            'MSFT' => $this->createCompany('MSFT', 2.0, 20.0, 15.0, 3.0, 0.5),
        ];

        $result = $this->transformer->transform($companies);

        // All companies included in average
        $this->assertEquals(2, $result->companiesIncluded);
        $this->assertEquals(60.0, $result->fwdPe); // (100 + 20) / 2
        $this->assertEquals(57.5, $result->evEbitda); // (100 + 15) / 2
        $this->assertEquals(51.5, $result->fcfYieldPercent); // (100 + 3.0) / 2
        $this->assertEquals(50.25, $result->divYieldPercent); // (100 + 0.5) / 2
        $this->assertEquals(2.5, $result->marketCapBillions); // (3.0 + 2.0) / 2
    }

    public function testReturnsNullWhenAllValuesNull(): void
    {
        $companies = [
            'MSFT' => $this->createCompanyWithNullValuation('MSFT', 2.0),
            'GOOGL' => $this->createCompanyWithNullValuation('GOOGL', 1.8),
        ];

        $result = $this->transformer->transform($companies);

        $this->assertEquals(2, $result->companiesIncluded);
        $this->assertNull($result->fwdPe);
        $this->assertNull($result->evEbitda);
        $this->assertNull($result->fcfYieldPercent);
        $this->assertNull($result->divYieldPercent);
        // Market cap is still present
        $this->assertEquals(1.9, $result->marketCapBillions); // (2.0 + 1.8) / 2
    }

    public function testHandlesSingleCompany(): void
    {
        $companies = [
            'MSFT' => $this->createCompany('MSFT', 2.5, 28.0, 17.0, 4.2, 0.9),
        ];

        $result = $this->transformer->transform($companies);

        $this->assertEquals(1, $result->companiesIncluded);
        $this->assertEquals(28.0, $result->fwdPe);
        $this->assertEquals(17.0, $result->evEbitda);
        $this->assertEquals(4.2, $result->fcfYieldPercent);
        $this->assertEquals(0.9, $result->divYieldPercent);
        $this->assertEquals(2.5, $result->marketCapBillions);
    }

    public function testHandlesNoCompanies(): void
    {
        $companies = [];

        $result = $this->transformer->transform($companies);

        $this->assertEquals(0, $result->companiesIncluded);
        $this->assertNull($result->fwdPe);
        $this->assertNull($result->evEbitda);
        $this->assertNull($result->fcfYieldPercent);
        $this->assertNull($result->divYieldPercent);
        $this->assertNull($result->marketCapBillions);
    }

    public function testHandlesPartialNullValues(): void
    {
        $companies = [
            'MSFT' => $this->createCompany('MSFT', 2.8, 30.0, null, 4.0, null),
            'GOOGL' => $this->createCompany('GOOGL', 1.8, null, 16.0, null, 0.0),
        ];

        $result = $this->transformer->transform($companies);

        $this->assertEquals(2, $result->companiesIncluded);
        // Only MSFT has fwd_pe
        $this->assertEquals(30.0, $result->fwdPe);
        // Only GOOGL has ev_ebitda
        $this->assertEquals(16.0, $result->evEbitda);
        // Only MSFT has fcf_yield
        $this->assertEquals(4.0, $result->fcfYieldPercent);
        // Only GOOGL has div_yield
        $this->assertEquals(0.0, $result->divYieldPercent);
    }

    private function createCompany(
        string $ticker,
        float $marketCapBillions,
        ?float $fwdPe,
        ?float $evEbitda,
        ?float $fcfYieldPercent,
        ?float $divYieldPercent
    ): CompanyData {
        return new CompanyData(
            ticker: $ticker,
            name: $ticker . ' Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoney($marketCapBillions * 1_000_000_000),
                fwdPe: $fwdPe !== null ? $this->createRatio($fwdPe) : null,
                evEbitda: $evEbitda !== null ? $this->createRatio($evEbitda) : null,
                fcfYield: $fcfYieldPercent !== null ? $this->createPercent($fcfYieldPercent) : null,
                divYield: $divYieldPercent !== null ? $this->createPercent($divYieldPercent) : null,
            ),
            financials: new FinancialsData(historyYears: 0, annualData: []),
            quarters: new QuartersData(quarters: []),
        );
    }

    private function createCompanyWithNullValuation(string $ticker, float $marketCapBillions): CompanyData
    {
        return new CompanyData(
            ticker: $ticker,
            name: $ticker . ' Inc',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoney($marketCapBillions * 1_000_000_000),
                fwdPe: null,
                evEbitda: null,
                fcfYield: null,
                divYield: null,
            ),
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
            sourceLocator: SourceLocator::json('$.marketCap', (string) $value),
        );
    }

    private function createRatio(float $value): DataPointRatio
    {
        return new DataPointRatio(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.ratio', (string) $value),
        );
    }

    private function createPercent(float $value): DataPointPercent
    {
        return new DataPointPercent(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Api,
            sourceLocator: SourceLocator::json('$.percent', (string) $value),
        );
    }
}
