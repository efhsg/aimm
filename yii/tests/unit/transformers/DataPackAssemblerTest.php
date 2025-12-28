<?php

declare(strict_types=1);

namespace tests\unit\transformers;

use app\dto\CollectionLog;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\FinancialsData;
use app\dto\MacroData;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\queries\DataPackRepository;
use app\transformers\DataPackAssembler;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;

/**
 * @covers \app\transformers\DataPackAssembler
 */
final class DataPackAssemblerTest extends Unit
{
    private string $tempDir;
    private DataPackRepository $repository;
    private DataPackAssembler $assembler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/datapack_assembler_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->repository = new DataPackRepository($this->tempDir);
        $this->assembler = new DataPackAssembler($this->repository);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testAssembleWritesValidDatapackJson(): void
    {
        $industryId = 'energy';
        $datapackId = 'dp-001';
        $this->repository->saveCompanyIntermediate(
            $industryId,
            $datapackId,
            $this->createCompanyData('AAA')
        );
        $this->repository->saveCompanyIntermediate(
            $industryId,
            $datapackId,
            $this->createCompanyData('BBB')
        );

        $macro = new MacroData(
            commodityBenchmark: $this->createMoneyDatapoint(82.5),
        );
        $log = $this->createCollectionLog([
            'AAA' => CollectionStatus::Complete,
            'BBB' => CollectionStatus::Complete,
        ]);
        $collectedAt = new DateTimeImmutable('2024-02-01T00:00:00Z');

        $path = $this->assembler->assemble(
            $industryId,
            $datapackId,
            $macro,
            $log,
            $collectedAt
        );

        $this->assertFileExists($path);

        $payload = json_decode((string) file_get_contents($path), true);
        $this->assertNotNull($payload);
        $this->assertSame($industryId, $payload['industry_id']);
        $this->assertSame($datapackId, $payload['datapack_id']);
        $this->assertSame($collectedAt->format(DateTimeImmutable::ATOM), $payload['collected_at']);
        $this->assertSame($macro->toArray(), $payload['macro']);
        $this->assertSame($log->toArray(), $payload['collection_log']);
        $this->assertCount(2, $payload['companies']);
        $this->assertEquals(
            $this->createCompanyData('AAA')->toArray(),
            $payload['companies']['AAA']
        );
        $this->assertEquals(
            $this->createCompanyData('BBB')->toArray(),
            $payload['companies']['BBB']
        );
    }

    public function testAssembleThrowsWhenOutputPathIsNotWritable(): void
    {
        $industryId = 'energy';
        $datapackId = 'dp-002';
        $outputPath = $this->repository->getDataPackPath($industryId, $datapackId);
        $outputDir = dirname($outputPath);
        $tmpPath = $outputPath . '.tmp';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        mkdir($tmpPath, 0755, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot open datapack file for writing');

        $this->assembler->assemble(
            $industryId,
            $datapackId,
            new MacroData(),
            $this->createCollectionLog([]),
            new DateTimeImmutable('2024-02-01T00:00:00Z')
        );
    }

    public function testAssembleThrowsWhenIntermediateFileMissing(): void
    {
        $industryId = 'energy';
        $datapackId = 'dp-003';
        $intermediateDir = $this->repository->getIntermediateDir($industryId, $datapackId);
        mkdir($intermediateDir, 0755, true);

        $missingPath = $intermediateDir . '/MISSING.json';
        symlink($intermediateDir . '/does-not-exist', $missingPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read intermediate file');

        $this->assembler->assemble(
            $industryId,
            $datapackId,
            new MacroData(),
            $this->createCollectionLog([]),
            new DateTimeImmutable('2024-02-01T00:00:00Z')
        );
    }

    private function createCompanyData(string $ticker): CompanyData
    {
        return new CompanyData(
            ticker: $ticker,
            name: $ticker . ' Corp',
            listingExchange: 'NYSE',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            valuation: new ValuationData(
                marketCap: $this->createMoneyDatapoint(100.0),
            ),
            financials: new FinancialsData(
                historyYears: 1,
                annualData: [],
            ),
            quarters: new QuartersData(quarters: []),
        );
    }

    /**
     * @param array<string, CollectionStatus> $companyStatuses
     */
    private function createCollectionLog(array $companyStatuses): CollectionLog
    {
        return new CollectionLog(
            startedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            completedAt: new DateTimeImmutable('2024-01-01T01:00:00Z'),
            durationSeconds: 3600,
            companyStatuses: $companyStatuses,
            macroStatus: CollectionStatus::Complete,
            totalAttempts: 0,
        );
    }

    private function createMoneyDatapoint(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable('2024-01-15'),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable('2024-01-16'),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('span.value', (string) $value),
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
