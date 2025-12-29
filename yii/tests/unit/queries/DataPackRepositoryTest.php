<?php

declare(strict_types=1);

namespace tests\unit\queries;

use app\dto\AnnualFinancials;
use app\dto\CollectionLog;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\GateResult;
use app\dto\GateWarning;
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
use InvalidArgumentException;

/**
 * @covers \app\queries\DataPackRepository
 */
final class DataPackRepositoryTest extends Unit
{
    private string $tempDir;
    private DataPackRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/datapack_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->repository = new DataPackRepository($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testGetBasePathReturnsConfiguredPath(): void
    {
        $this->assertSame($this->tempDir, $this->repository->getBasePath());
    }

    public function testGetDataPackPathReturnsCorrectPath(): void
    {
        $expected = "{$this->tempDir}/oil-majors/abc123/datapack.json";
        $this->assertSame($expected, $this->repository->getDataPackPath('oil-majors', 'abc123'));
    }

    public function testGetIntermediateDirReturnsCorrectPath(): void
    {
        $expected = "{$this->tempDir}/oil-majors/abc123/intermediate";
        $this->assertSame($expected, $this->repository->getIntermediateDir('oil-majors', 'abc123'));
    }

    public function testSaveAndLoadRoundTrip(): void
    {
        $dataPack = $this->createTestDataPack();

        $path = $this->repository->save($dataPack);

        $this->assertFileExists($path);

        $loaded = $this->repository->load('oil-majors', 'test-datapack-123');

        $this->assertNotNull($loaded);
        $this->assertSame($dataPack->industryId, $loaded->industryId);
        $this->assertSame($dataPack->datapackId, $loaded->datapackId);
        $this->assertCount(1, $loaded->companies);
        $this->assertTrue($loaded->hasCompany('SHEL'));
    }

    public function testLoadReturnsNullForNonexistentDataPack(): void
    {
        $result = $this->repository->load('nonexistent', 'nonexistent');
        $this->assertNull($result);
    }

    public function testExistsReturnsTrueForExistingDataPack(): void
    {
        $dataPack = $this->createTestDataPack();
        $this->repository->save($dataPack);

        $this->assertTrue($this->repository->exists('oil-majors', 'test-datapack-123'));
    }

    public function testExistsReturnsFalseForNonexistentDataPack(): void
    {
        $this->assertFalse($this->repository->exists('nonexistent', 'nonexistent'));
    }

    public function testSaveCompanyIntermediateAndLoad(): void
    {
        $company = $this->createTestCompanyData('SHEL');

        $this->repository->saveCompanyIntermediate('oil-majors', 'abc123', $company);

        $loaded = $this->repository->loadCompanyIntermediate('oil-majors', 'abc123', 'SHEL');

        $this->assertNotNull($loaded);
        $this->assertSame('SHEL', $loaded->ticker);
        $this->assertSame('Shell plc', $loaded->name);
    }

    public function testLoadCompanyIntermediateReturnsNullForNonexistent(): void
    {
        $result = $this->repository->loadCompanyIntermediate('oil-majors', 'abc123', 'NONEXISTENT');
        $this->assertNull($result);
    }

    public function testListIntermediateTickersReturnsAllTickers(): void
    {
        $this->repository->saveCompanyIntermediate('oil-majors', 'abc123', $this->createTestCompanyData('SHEL'));
        $this->repository->saveCompanyIntermediate('oil-majors', 'abc123', $this->createTestCompanyData('XOM'));
        $this->repository->saveCompanyIntermediate('oil-majors', 'abc123', $this->createTestCompanyData('CVX'));

        $tickers = $this->repository->listIntermediateTickers('oil-majors', 'abc123');

        $this->assertCount(3, $tickers);
        $this->assertSame(['CVX', 'SHEL', 'XOM'], $tickers); // Sorted alphabetically
    }

    public function testListIntermediateTickersReturnsEmptyForNonexistent(): void
    {
        $tickers = $this->repository->listIntermediateTickers('nonexistent', 'nonexistent');
        $this->assertSame([], $tickers);
    }

    public function testSaveValidationAndVerify(): void
    {
        $gateResult = new GateResult(
            passed: true,
            errors: [],
            warnings: [
                new GateWarning('W001', 'Optional field missing', 'companies.SHEL'),
            ],
        );

        $path = $this->repository->saveValidation('oil-majors', 'abc123', $gateResult);

        $this->assertFileExists($path);

        $content = json_decode(file_get_contents($path), true);
        $this->assertTrue($content['passed']);
        $this->assertCount(0, $content['errors']);
        $this->assertCount(1, $content['warnings']);
        $this->assertSame('W001', $content['warnings'][0]['code']);
        $this->assertArrayHasKey('validated_at', $content);
    }

    public function testSaveCollectionLogWritesReadableLog(): void
    {
        $log = new CollectionLog(
            startedAt: new DateTimeImmutable('2024-01-01 10:00:00'),
            completedAt: new DateTimeImmutable('2024-01-01 10:05:00'),
            durationSeconds: 300,
            companyStatuses: [
                'AAPL' => CollectionStatus::Complete,
                'MSFT' => CollectionStatus::Partial,
            ],
            macroStatus: CollectionStatus::Complete,
            totalAttempts: 15,
        );

        $path = $this->repository->saveCollectionLog('energy', 'dp-123', $log);

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString('Collection Log', $content);
        $this->assertStringContainsString('AAPL', $content);
        $this->assertStringContainsString('MSFT', $content);
        $this->assertStringContainsString('300 seconds', $content);
        $this->assertStringContainsString('Total Attempts: 15', $content);
    }

    public function testListByIndustryReturnsDatapacksSortedByDate(): void
    {
        // Create first datapack
        $dataPack1 = $this->createTestDataPack('datapack-001');
        $this->repository->save($dataPack1);

        // Touch the first datapack directory to set an older timestamp
        $dir1 = "{$this->tempDir}/oil-majors/datapack-001";
        touch($dir1, time() - 3600); // 1 hour ago

        // Create second datapack (will have current timestamp)
        $dataPack2 = $this->createTestDataPack('datapack-002');
        $this->repository->save($dataPack2);

        $list = $this->repository->listByIndustry('oil-majors');

        $this->assertCount(2, $list);
        // Newest first
        $this->assertSame('datapack-002', $list[0]['datapack_id']);
        $this->assertSame('datapack-001', $list[1]['datapack_id']);
    }

    public function testListByIndustryReturnsEmptyForNonexistentIndustry(): void
    {
        $list = $this->repository->listByIndustry('nonexistent');
        $this->assertSame([], $list);
    }

    public function testGetLatestReturnsNewestDatapack(): void
    {
        $dataPack1 = $this->createTestDataPack('datapack-001');
        $this->repository->save($dataPack1);

        // Touch the first datapack directory to set an older timestamp
        $dir1 = "{$this->tempDir}/oil-majors/datapack-001";
        touch($dir1, time() - 3600); // 1 hour ago

        $dataPack2 = $this->createTestDataPack('datapack-002');
        $this->repository->save($dataPack2);

        $latest = $this->repository->getLatest('oil-majors');

        $this->assertNotNull($latest);
        $this->assertSame('datapack-002', $latest->datapackId);
    }

    public function testGetLatestReturnsNullForNonexistentIndustry(): void
    {
        $latest = $this->repository->getLatest('nonexistent');
        $this->assertNull($latest);
    }

    public function testDeleteRemovesDatapackDirectory(): void
    {
        $dataPack = $this->createTestDataPack();
        $this->repository->save($dataPack);
        $this->repository->saveCompanyIntermediate('oil-majors', 'test-datapack-123', $this->createTestCompanyData('SHEL'));

        $this->assertTrue($this->repository->exists('oil-majors', 'test-datapack-123'));

        $this->repository->delete('oil-majors', 'test-datapack-123');

        $this->assertFalse($this->repository->exists('oil-majors', 'test-datapack-123'));
    }

    public function testDeleteDoesNothingForNonexistent(): void
    {
        // Should not throw
        $this->repository->delete('nonexistent', 'nonexistent');
        $this->assertTrue(true);
    }

    public function testSafePathSegmentRejectsInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path segment: ../hack');

        $this->repository->getDataPackPath('../hack', 'test');
    }

    public function testSafePathSegmentRejectsSlashes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path segment: foo/bar');

        $this->repository->getDataPackPath('foo/bar', 'test');
    }

    public function testSafePathSegmentAllowsValidCharacters(): void
    {
        $path = $this->repository->getDataPackPath('oil-majors_2024', 'abc-123_XYZ');
        $this->assertStringContainsString('oil-majors_2024', $path);
        $this->assertStringContainsString('abc-123_XYZ', $path);
    }

    private function createTestDataPack(string $datapackId = 'test-datapack-123'): IndustryDataPack
    {
        $now = new DateTimeImmutable();
        $company = $this->createTestCompanyData('SHEL');

        return new IndustryDataPack(
            industryId: 'oil-majors',
            datapackId: $datapackId,
            collectedAt: $now,
            macro: new MacroData(
                commodityBenchmark: $this->createTestMoneyDataPoint(75.50),
                marginProxy: null,
                sectorIndex: null,
            ),
            companies: ['SHEL' => $company],
            collectionLog: new CollectionLog(
                startedAt: $now,
                completedAt: $now,
                durationSeconds: 120,
                companyStatuses: ['SHEL' => CollectionStatus::Complete],
                macroStatus: CollectionStatus::Complete,
                totalAttempts: 5,
            ),
        );
    }

    private function createTestCompanyData(string $ticker): CompanyData
    {
        return new CompanyData(
            ticker: $ticker,
            name: $ticker === 'SHEL' ? 'Shell plc' : $ticker,
            listingExchange: 'LSE',
            listingCurrency: 'GBP',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createTestMoneyDataPoint(180_000_000_000),
            ),
            financials: new FinancialsData(
                historyYears: 3,
                annualData: [
                    2024 => new AnnualFinancials(
                        fiscalYear: 2024,
                        revenue: $this->createTestMoneyDataPoint(300_000_000_000),
                    ),
                ],
            ),
            quarters: new QuartersData(
                quarters: [
                    '2024Q3' => new QuarterFinancials(
                        fiscalYear: 2024,
                        fiscalQuarter: 3,
                        periodEnd: new DateTimeImmutable('2024-09-30'),
                        revenue: $this->createTestMoneyDataPoint(75_000_000_000),
                    ),
                ],
            ),
        );
    }

    private function createTestMoneyDataPoint(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com/data',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('div.value', (string) $value),
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
