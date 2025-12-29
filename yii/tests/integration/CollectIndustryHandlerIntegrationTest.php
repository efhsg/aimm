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
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\collection\CollectDatapointHandler;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectMacroHandler;
use app\models\CollectionError;
use app\models\CollectionRun;
use app\models\IndustryConfig as IndustryConfigRecord;
use app\queries\CollectionRunRepository;
use app\queries\DataPackRepository;
use app\transformers\DataPackAssembler;
use app\validators\CollectionGateValidator;
use app\validators\SchemaValidator;
use app\validators\SemanticValidator;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;
use Yii;

/**
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
        $this->seedIndustryConfig();
    }

    protected function tearDown(): void
    {
        CollectionError::deleteAll();
        CollectionRun::deleteAll();
        IndustryConfigRecord::deleteAll();
        $this->deleteDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testCollectsIndustryAndPassesGate(): void
    {
        $handler = $this->createHandler([
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/AAPL-quote.html'),
                'contentType' => 'text/html',
            ],
        ]);

        $result = $handler->collect($this->createRequest());

        $this->assertTrue(
            $result->gateResult->passed,
            'Gate failed: ' . implode('; ', array_map(
                static fn ($error): string => "{$error->code} {$error->message}",
                $result->gateResult->errors
            ))
        );
        $this->assertSame(CollectionStatus::Complete, $result->overallStatus);
        $this->assertFileExists($result->dataPackPath);
    }

    public function testGateFailureMarksRunFailed(): void
    {
        $handler = $this->createHandler([
            'https://finance.yahoo.com/quote/AAPL' => [
                'path' => $this->fixturePath('yahoo-finance/invalid-page.html'),
                'contentType' => 'text/html',
            ],
        ]);

        $result = $handler->collect($this->createRequest());

        $this->assertFalse($result->gateResult->passed);
        $this->assertSame(CollectionStatus::Failed, $result->overallStatus);
        $this->assertNotEmpty($result->gateResult->errors);
    }

    private function createHandler(array $fixtures): CollectIndustryHandler
    {
        $repository = new DataPackRepository($this->tempDir);
        $assembler = new DataPackAssembler($repository);

        $blockedPath = $this->tempDir . '/blocked-sources.json';
        $adapterChain = new AdapterChain(
            [new YahooFinanceAdapter()],
            new BlockedSourceRegistry($blockedPath),
            Yii::getLogger(),
        );

        $fetchClient = new FixtureWebFetchClient($fixtures);
        $dataPointFactory = new DataPointFactory();
        $datapointHandler = new CollectDatapointHandler(
            $fetchClient,
            $adapterChain,
            $dataPointFactory,
            Yii::getLogger(),
        );

        $companyHandler = new CollectCompanyHandler(
            $datapointHandler,
            new SourceCandidateFactory(),
            $dataPointFactory,
            Yii::getLogger(),
        );

        $macroHandler = new CollectMacroHandler(
            $datapointHandler,
            new SourceCandidateFactory(),
            $dataPointFactory,
            Yii::getLogger(),
        );

        $schemaValidator = new SchemaValidator(Yii::$app->basePath . '/config/schemas');
        $semanticValidator = new SemanticValidator();
        $gateValidator = new CollectionGateValidator($schemaValidator, $semanticValidator);

        return new CollectIndustryHandler(
            companyCollector: $companyHandler,
            macroCollector: $macroHandler,
            repository: $repository,
            assembler: $assembler,
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
            id: 'tech',
            name: 'Technology',
            sector: 'Software',
            companies: [$company],
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

    private function seedIndustryConfig(): void
    {
        CollectionError::deleteAll();
        CollectionRun::deleteAll();
        IndustryConfigRecord::deleteAll();

        $record = new IndustryConfigRecord();
        $record->industry_id = 'tech';
        $record->name = 'Technology';
        $record->config_yaml = json_encode([
            'id' => 'tech',
            'name' => 'Technology',
            'sector' => 'Software',
            'companies' => [],
            'macro_requirements' => new \stdClass(),
            'data_requirements' => [
                'history_years' => 1,
                'quarters_to_fetch' => 0,
                'valuation_metrics' => [],
                'annual_financial_metrics' => [],
                'quarter_metrics' => [],
                'operational_metrics' => [],
            ],
        ], JSON_THROW_ON_ERROR);
        $record->save(false);
    }
}

/**
 * @internal
 */
final class FixtureWebFetchClient implements WebFetchClientInterface
{
    /**
     * @param array<string, array{path: string, contentType: string}> $fixtures
     */
    public function __construct(
        private readonly array $fixtures,
    ) {
    }

    public function fetch(FetchRequest $request): FetchResult
    {
        $fixture = $this->fixtures[$request->url] ?? null;
        if ($fixture === null) {
            return new FetchResult(
                content: '',
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

/**
 * @internal
 */
/**
 * @internal
 */
