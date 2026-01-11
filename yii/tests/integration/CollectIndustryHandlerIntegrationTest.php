<?php

declare(strict_types=1);

namespace tests\integration;

use app\adapters\AdapterChain;
use app\adapters\BlockedSourceRegistry;
use app\adapters\YahooFinanceAdapter;
use app\alerts\AlertDispatcher;
use app\clients\FetchRequest;
use app\clients\WebFetchClientInterface;
use app\dto\CollectIndustryRequest;
use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\FetchResult;
use app\dto\IndustryConfig;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\enums\CollectionStatus;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use app\handlers\collection\CollectBatchHandler;
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\collection\CollectDatapointHandler;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectMacroHandler;
use app\models\CollectionError;
use app\models\CollectionRun;
use app\queries\AnnualFinancialQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\MacroIndicatorQuery;
use app\queries\PriceHistoryQuery;
use app\queries\QuarterlyFinancialQuery;
use app\queries\ValuationSnapshotQuery;
use app\validators\CollectionGateValidator;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;
use Yii;

/**
 * Integration test for CollectIndustryHandler with dossier architecture.
 *
 * Tests the full collection flow using fixture-based web fetching
 * and real database interactions for collection_run tracking.
 *
 * @covers \app\handlers\collection\CollectIndustryHandler
 */
final class CollectIndustryHandlerIntegrationTest extends Unit
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/collect_industry_integration_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->cleanDatabase();
        $this->cleanTestData();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        $this->cleanTestData();
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        CollectionError::deleteAll();
        CollectionRun::deleteAll();
    }

    private function seedTestData(): void
    {
        // Seed sector and industry for FK constraint
        $db = Yii::$app->db;

        $db->createCommand()->insert('sector', [
            'slug' => 'software',
            'name' => 'Software',
        ])->execute();
        $sectorId = (int) $db->getLastInsertID();

        $db->createCommand()->insert('industry', [
            'slug' => 'tech',
            'name' => 'Technology Test',
            'sector_id' => $sectorId,
            'is_active' => 1,
            'created_by' => 'test',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ])->execute();
    }

    private function cleanTestData(): void
    {
        $db = Yii::$app->db;
        $db->createCommand()->delete('industry', ['slug' => 'tech'])->execute();
        $db->createCommand()->delete('sector', ['slug' => 'software'])->execute();
    }

    public function testCollectsIndustryAndRecordsRun(): void
    {
        $fixtures = [
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/AAPL-quote.html'),
                'contentType' => 'text/html',
            ],
        ];

        $handler = $this->createHandler($fixtures);
        $request = $this->createRequest();

        $result = $handler->collect($request);

        // Verify result structure
        $this->assertSame('tech', $result->industryId);
        $this->assertNotEmpty($result->datapackId);
        $this->assertInstanceOf(\app\dto\GateResult::class, $result->gateResult);
        $this->assertArrayHasKey('AAPL', $result->companyStatuses);

        // Verify collection run was recorded
        $run = CollectionRun::findOne(['datapack_id' => $result->datapackId]);
        $this->assertNotNull($run, 'Collection run should be recorded in database');
        $this->assertSame($this->getIndustryId(), (int) $run->industry_id); // industry_id is now an integer FK
        $this->assertNotNull($run->completed_at);
        $this->assertSame(1, (int) $run->companies_total);
    }

    public function testGateFailsWhenCompanyCollectionFails(): void
    {
        // Use non-existent fixture to simulate collection failure
        $fixtures = [
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/not-found.html'),
                'contentType' => 'text/html',
            ],
        ];

        $handler = $this->createHandler($fixtures);
        $request = $this->createRequest();

        $result = $handler->collect($request);

        // Gate should fail because company collection failed
        $this->assertFalse($result->gateResult->passed);
        $this->assertSame(CollectionStatus::Failed, $result->overallStatus);

        // Verify errors were recorded
        $run = CollectionRun::findOne(['datapack_id' => $result->datapackId]);
        $this->assertNotNull($run);
        $this->assertSame(0, (int) $run->gate_passed);
        $this->assertGreaterThan(0, (int) $run->error_count);

        // Verify collection errors are stored
        $errors = CollectionError::findAll(['collection_run_id' => $run->id]);
        $this->assertNotEmpty($errors);
    }

    public function testMultipleCompaniesWithMixedResults(): void
    {
        $config = $this->createIndustryConfigWithMultipleCompanies();

        $fixtures = [
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/AAPL-quote.html'),
                'contentType' => 'text/html',
            ],
            // MSFT will fail (no fixture)
        ];

        $handler = $this->createHandler($fixtures);
        $request = new CollectIndustryRequest(
            config: $config,
            batchSize: 2,
            enableMemoryManagement: false,
        );

        $result = $handler->collect($request);

        // AAPL should succeed, MSFT will fail (no fixture)
        $this->assertArrayHasKey('AAPL', $result->companyStatuses);
        $this->assertArrayHasKey('MSFT', $result->companyStatuses);

        // Gate should fail because of MSFT failure
        $this->assertFalse($result->gateResult->passed);

        // But there should be warnings about the failed company
        $this->assertNotEmpty($result->gateResult->errors);
    }

    public function testCollectionCancellation(): void
    {
        $config = $this->createIndustryConfigWithMultipleCompanies(); // AAPL and MSFT

        $fixtures = [
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/AAPL-quote.html'),
                'contentType' => 'text/html',
            ],
        ];

        // Callback to cancel the run during the first fetch
        $onFetch = function () {
            $run = CollectionRun::find()
                ->orderBy(['id' => SORT_DESC])
                ->limit(1)
                ->one();

            if ($run) {
                $run->cancel_requested = true;
                $run->save(false, ['cancel_requested']);
            }
        };

        $handler = $this->createHandler($fixtures, $onFetch);

        // Use batchSize 1 so cancellation check happens between AAPL and MSFT
        $request = new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        );

        $result = $handler->collect($request);

        // Should be cancelled
        $this->assertSame(CollectionStatus::Cancelled, $result->overallStatus);

        // AAPL should be processed (status could be Complete or Failed depending on when cancellation happened relative to status update)
        // In our handler, company status is set after collection.
        // But if cancellation happens during fetch, AAPL collection finishes, then status is recorded.
        // Then loop checks cancellation before MSFT.

        $this->assertArrayHasKey('AAPL', $result->companyStatuses);
        $this->assertSame(CollectionStatus::Complete, $result->companyStatuses['AAPL']);

        // MSFT should NOT be processed
        $this->assertArrayNotHasKey('MSFT', $result->companyStatuses);

        // Verify run status in DB
        $run = CollectionRun::findOne(['datapack_id' => $result->datapackId]);
        $this->assertSame(CollectionRun::STATUS_CANCELLED, $run->status);
    }

    private function createHandler(array $fixtures, ?callable $onFetch = null): CollectIndustryHandler
    {
        $blockedPath = $this->tempDir . '/blocked-sources.json';
        $adapterChain = new AdapterChain(
            [new YahooFinanceAdapter()],
            new BlockedSourceRegistry($blockedPath),
            Yii::getLogger(),
        );

        $fetchClient = new FixtureWebFetchClient($fixtures, $onFetch);
        $dataPointFactory = new DataPointFactory();
        $sourceCandidateFactory = new SourceCandidateFactory();

        $datapointHandler = new CollectDatapointHandler(
            $fetchClient,
            $adapterChain,
            $dataPointFactory,
            Yii::getLogger(),
        );

        $batchHandler = new CollectBatchHandler(
            $fetchClient,
            $adapterChain,
            Yii::getLogger(),
        );

        // Create query mocks that don't actually write to DB
        // (we're testing the handler orchestration, not DB persistence)
        $companyQuery = $this->createMock(CompanyQuery::class);
        $companyQuery->method('findOrCreate')->willReturn(1);

        $annualQuery = $this->createMock(AnnualFinancialQuery::class);
        $quarterlyQuery = $this->createMock(QuarterlyFinancialQuery::class);
        $valuationQuery = $this->createMock(ValuationSnapshotQuery::class);
        $macroQuery = $this->createMock(MacroIndicatorQuery::class);
        $priceQuery = $this->createMock(PriceHistoryQuery::class);

        $companyHandler = new CollectCompanyHandler(
            $datapointHandler,
            $batchHandler,
            $sourceCandidateFactory,
            $dataPointFactory,
            Yii::getLogger(),
            $companyQuery,
            $annualQuery,
            $quarterlyQuery,
            $valuationQuery,
        );

        $macroHandler = new CollectMacroHandler(
            $datapointHandler,
            $batchHandler,
            $sourceCandidateFactory,
            $dataPointFactory,
            Yii::getLogger(),
            $macroQuery,
            $priceQuery,
        );

        $gateValidator = new CollectionGateValidator();

        return new CollectIndustryHandler(
            companyCollector: $companyHandler,
            macroCollector: $macroHandler,
            gateValidator: $gateValidator,
            alertDispatcher: new AlertDispatcher([]),
            runRepository: new CollectionRunRepository(Yii::$app->db),
            logger: Yii::getLogger(),
        );
    }

    private function createRequest(): CollectIndustryRequest
    {
        return new CollectIndustryRequest(
            config: $this->createIndustryConfig(),
            batchSize: 1,
            enableMemoryManagement: false,
        );
    }

    private function getIndustryId(): int
    {
        return (int) Yii::$app->db->createCommand('SELECT id FROM industry WHERE slug = :slug')
            ->bindValue(':slug', 'tech')
            ->queryScalar();
    }

    private function createIndustryConfig(): IndustryConfig
    {
        $company = new CompanyConfig(
            ticker: 'AAPL',
            name: 'Apple Inc.',
            listingExchange: 'NASDAQ',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            fyEndMonth: 12,
        );

        $requirements = new DataRequirements(
            historyYears: 1,
            quartersToFetch: 0,
            valuationMetrics: [
                new MetricDefinition(key: 'market_cap', unit: 'currency', required: true),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        return new IndustryConfig(
            industryId: $this->getIndustryId(),
            id: 'tech',
            name: 'Technology',
            sector: 'Software',
            companies: [$company],
            macroRequirements: new MacroRequirements(),
            dataRequirements: $requirements,
        );
    }

    private function createIndustryConfigWithMultipleCompanies(): IndustryConfig
    {
        $companies = [
            new CompanyConfig(
                ticker: 'AAPL',
                name: 'Apple Inc.',
                listingExchange: 'NASDAQ',
                listingCurrency: 'USD',
                reportingCurrency: 'USD',
                fyEndMonth: 12,
            ),
            new CompanyConfig(
                ticker: 'MSFT',
                name: 'Microsoft Corp.',
                listingExchange: 'NASDAQ',
                listingCurrency: 'USD',
                reportingCurrency: 'USD',
                fyEndMonth: 6,
            ),
        ];

        $requirements = new DataRequirements(
            historyYears: 1,
            quartersToFetch: 0,
            valuationMetrics: [
                new MetricDefinition(key: 'market_cap', unit: 'currency', required: true),
            ],
            annualFinancialMetrics: [],
            quarterMetrics: [],
            operationalMetrics: [],
        );

        return new IndustryConfig(
            industryId: $this->getIndustryId(),
            id: 'tech',
            name: 'Technology',
            sector: 'Software',
            companies: $companies,
            macroRequirements: new MacroRequirements(),
            dataRequirements: $requirements,
        );
    }

    private function fixturePath(string $relativePath): string
    {
        return dirname(__DIR__) . '/fixtures/' . $relativePath;
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

/**
 * Mock web fetch client that returns fixture content.
 *
 * @internal
 */
final class FixtureWebFetchClient implements WebFetchClientInterface
{
    /**
     * @param array<string, array{path: string, contentType: string}> $fixtures
     */
    public function __construct(
        private readonly array $fixtures,
        private readonly mixed $onFetch = null,
    ) {
    }

    public function fetch(FetchRequest $request): FetchResult
    {
        if ($this->onFetch) {
            ($this->onFetch)($request);
        }

        $fixture = $this->fixtures[$request->url] ?? null;
        if ($fixture === null) {
            return new FetchResult(
                content: '<html><body>Not Found</body></html>',
                contentType: 'text/html',
                statusCode: 404,
                url: $request->url,
                finalUrl: $request->url,
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            );
        }

        if (!file_exists($fixture['path'])) {
            return new FetchResult(
                content: '<html><body>Fixture Not Found</body></html>',
                contentType: 'text/html',
                statusCode: 404,
                url: $request->url,
                finalUrl: $request->url,
                retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            );
        }

        $content = file_get_contents($fixture['path']);
        if ($content === false) {
            throw new RuntimeException('Failed to load fixture: ' . $fixture['path']);
        }

        return new FetchResult(
            content: $content,
            contentType: $fixture['contentType'],
            statusCode: 200,
            url: $request->url,
            finalUrl: $request->url,
            retrievedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
        );
    }

    public function isRateLimited(string $domain): bool
    {
        return false;
    }
}
