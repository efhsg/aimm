<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\CachedDataAdapter;
use app\dto\AdaptRequest;
use app\dto\AnnualFinancials;
use app\dto\CollectionLog;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\FetchResult;
use app\dto\FinancialsData;
use app\dto\IndustryDataPack;
use app\dto\MacroData;
use app\dto\QuarterFinancials;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\queries\DataPackRepository;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\CachedDataAdapter
 */
final class CachedDataAdapterTest extends Unit
{
    private string $tempDir;
    private DataPackRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/cached-adapter-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->repository = new DataPackRepository($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testReturnsExtractionsWhenCacheIsValid(): void
    {
        $collectedAt = new DateTimeImmutable('-2 days');
        $dataPack = $this->createDataPack(
            datapackId: 'pack-001',
            collectedAt: $collectedAt,
            companies: [
                'SHEL' => $this->createCompanyData('SHEL', $this->createRatioDataPoint(12.5)),
            ],
        );

        $this->repository->save($dataPack);

        $adapter = new CachedDataAdapter($this->repository, 'oil-majors');
        $request = $this->createRequest(['valuation.market_cap', 'valuation.fwd_pe'], 'SHEL');

        $result = $adapter->adapt($request);

        $this->assertCount(2, $result->extractions);
        $this->assertSame([], $result->notFound);
        $this->assertNotNull($result->parseError);

        $marketCap = $result->extractions['valuation.market_cap'];
        $expectedAge = (int) (new DateTimeImmutable())->diff($collectedAt)->days;

        $this->assertSame(180_000_000_000.0, $marketCap->rawValue);
        $this->assertSame('currency', $marketCap->unit);
        $this->assertSame('USD', $marketCap->currency);
        $this->assertSame('units', $marketCap->scale);
        $this->assertSame("cache://oil-majors/pack-001", $marketCap->cacheSource);
        $this->assertSame($expectedAge, $marketCap->cacheAgeDays);
        $this->assertSame(
            'cache://oil-majors/pack-001/companies/SHEL/valuation/market_cap',
            $marketCap->locator->selector
        );

        $fwdPe = $result->extractions['valuation.fwd_pe'];
        $this->assertSame(12.5, $fwdPe->rawValue);
        $this->assertSame('ratio', $fwdPe->unit);
        $this->assertNull($fwdPe->currency);
        $this->assertNull($fwdPe->scale);
    }

    public function testReturnsNotFoundWhenCacheIsExpired(): void
    {
        $collectedAt = new DateTimeImmutable('-10 days');
        $dataPack = $this->createDataPack(
            datapackId: 'pack-002',
            collectedAt: $collectedAt,
            companies: [
                'SHEL' => $this->createCompanyData('SHEL', $this->createRatioDataPoint(14.2)),
            ],
        );

        $this->repository->save($dataPack);

        $adapter = new CachedDataAdapter($this->repository, 'oil-majors');
        $request = $this->createRequest(['valuation.market_cap'], 'SHEL');

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->extractions);
        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertStringContainsString('Cached datapack too old', $result->parseError ?? '');
    }

    public function testReturnsNotFoundWhenNoDatapackExists(): void
    {
        $adapter = new CachedDataAdapter($this->repository, 'oil-majors');
        $request = $this->createRequest(['valuation.market_cap'], 'SHEL');

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->extractions);
        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertSame('No cached datapack available', $result->parseError);
    }

    public function testReturnsNotFoundWhenTickerMissingInCache(): void
    {
        $dataPack = $this->createDataPack(
            datapackId: 'pack-003',
            collectedAt: new DateTimeImmutable('-1 day'),
            companies: [
                'SHEL' => $this->createCompanyData('SHEL', $this->createRatioDataPoint(9.1)),
            ],
        );

        $this->repository->save($dataPack);

        $adapter = new CachedDataAdapter($this->repository, 'oil-majors');
        $request = $this->createRequest(['valuation.market_cap'], 'AAPL');

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->extractions);
        $this->assertSame(['valuation.market_cap'], $result->notFound);
        $this->assertSame('Ticker not in cached datapack', $result->parseError);
    }

    /**
     * @param array<string, CompanyData> $companies
     */
    private function createDataPack(
        string $datapackId,
        DateTimeImmutable $collectedAt,
        array $companies,
    ): IndustryDataPack {
        return new IndustryDataPack(
            industryId: 'oil-majors',
            datapackId: $datapackId,
            collectedAt: $collectedAt,
            macro: new MacroData(),
            companies: $companies,
            collectionLog: new CollectionLog(
                startedAt: $collectedAt,
                completedAt: $collectedAt,
                durationSeconds: 120,
                companyStatuses: array_fill_keys(array_keys($companies), CollectionStatus::Complete),
                macroStatus: CollectionStatus::Complete,
                totalAttempts: 1,
            ),
        );
    }

    private function createCompanyData(string $ticker, DataPointRatio $fwdPe): CompanyData
    {
        return new CompanyData(
            ticker: $ticker,
            name: $ticker . ' Plc',
            listingExchange: 'NYSE',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoneyDataPoint(180_000_000_000.0),
                fwdPe: $fwdPe,
            ),
            financials: new FinancialsData(
                historyYears: 1,
                annualData: [
                    2024 => new AnnualFinancials(
                        fiscalYear: 2024,
                        revenue: $this->createMoneyDataPoint(50_000_000_000.0),
                    ),
                ],
            ),
            quarters: new QuartersData(
                quarters: [
                    '2024Q4' => new QuarterFinancials(
                        fiscalYear: 2024,
                        fiscalQuarter: 4,
                        periodEnd: new DateTimeImmutable('2024-12-31'),
                        revenue: $this->createMoneyDataPoint(12_500_000_000.0),
                    ),
                ],
            ),
        );
    }

    private function createMoneyDataPoint(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-15'),
            sourceUrl: 'https://example.com/source',
            retrievedAt: new DateTimeImmutable('2024-01-16'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('div.value', (string) $value),
        );
    }

    private function createRatioDataPoint(float $value): DataPointRatio
    {
        return new DataPointRatio(
            value: $value,
            asOf: new DateTimeImmutable('2024-01-15'),
            sourceUrl: 'https://example.com/source',
            retrievedAt: new DateTimeImmutable('2024-01-16'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('div.value', (string) $value),
        );
    }

    /**
     * @param list<string> $datapointKeys
     */
    private function createRequest(array $datapointKeys, string $ticker): AdaptRequest
    {
        $fetchResult = new FetchResult(
            content: '<html></html>',
            contentType: 'text/html',
            statusCode: 200,
            url: 'https://example.com',
            finalUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
        );

        return new AdaptRequest(
            fetchResult: $fetchResult,
            datapointKeys: $datapointKeys,
            ticker: $ticker,
        );
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
