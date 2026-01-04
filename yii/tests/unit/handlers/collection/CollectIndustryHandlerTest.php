<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\alerts\AlertDispatcher;
use app\alerts\AlertNotifierInterface;
use app\alerts\CollectionAlertEvent;
use app\dto\CollectCompanyRequest;
use app\dto\CollectCompanyResult;
use app\dto\CollectIndustryRequest;
use app\dto\CollectMacroResult;
use app\dto\CompanyConfig;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\DataRequirements;
use app\dto\FinancialsData;
use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\dto\MacroData;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\handlers\collection\CollectCompanyInterface;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectMacroInterface;
use app\queries\CollectionRunRepository;
use app\validators\CollectionGateValidatorInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\db\Connection;
use yii\log\Logger;

final class CollectIndustryHandlerTest extends Unit
{
    private ?Connection $runDb = null;

    protected function _after(): void
    {
        if ($this->runDb !== null) {
            $this->runDb->close();
            $this->runDb = null;
        }
    }

    public function testCollectsIndustrySuccessfully(): void
    {
        $config = $this->createIndustryConfig(['AAA', 'BBB']);
        $macroResult = new CollectMacroResult(
            data: new MacroData(commodityBenchmark: $this->createMoneyDatapoint(85.0)),
            sourceAttempts: [],
            status: CollectionStatus::Complete,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->method('collect')
            ->willReturnCallback(function (CollectCompanyRequest $request): CollectCompanyResult {
                return $this->createCompanyResult($request->config, CollectionStatus::Complete);
            });

        $gateResult = new GateResult(true, [], []);
        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validateResults')->willReturn($gateResult);

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $runRepository = $this->createRunRepository();

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $gateValidator,
            $alertDispatcher,
            $runRepository
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        ));

        $this->assertSame($config->id, $result->industryId);
        $this->assertNotSame('', $result->datapackId);
        $this->assertSame(CollectionStatus::Complete, $result->overallStatus);
        $this->assertSame($gateResult, $result->gateResult);
        $this->assertCount(2, $result->companyStatuses);
        $this->assertSame(CollectionStatus::Complete, $result->companyStatuses['AAA']);
        $this->assertSame(CollectionStatus::Complete, $result->companyStatuses['BBB']);
        $this->assertSame([], $alertNotifier->events);

        $run = $this->getRunRow();
        $this->assertSame($config->id, $run['industry_id']);
        $this->assertSame($result->datapackId, $run['datapack_id']);
        $this->assertSame(CollectionStatus::Complete->value, $run['status']);
        $this->assertSame(2, (int) $run['companies_total']);
        $this->assertSame(2, (int) $run['companies_success']);
        $this->assertSame(0, (int) $run['companies_failed']);
        $this->assertSame(1, (int) $run['gate_passed']);
        $this->assertSame(0, (int) $run['error_count']);
        $this->assertSame(0, (int) $run['warning_count']);
    }

    public function testReturnsPartialWhenMacroFails(): void
    {
        $config = $this->createIndustryConfig(['AAA']);
        $macroResult = new CollectMacroResult(
            data: new MacroData(),
            sourceAttempts: [],
            status: CollectionStatus::Failed,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->expects($this->once())
            ->method('collect')
            ->willReturn($this->createCompanyResult(
                $config->companies[0],
                CollectionStatus::Complete
            ));

        // Macro failure triggers gate error
        $gateResult = new GateResult(false, [new \app\dto\GateError('MACRO_FAILED', 'Macro failed', 'macro')], []);
        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validateResults')->willReturn($gateResult);

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $runRepository = $this->createRunRepository();

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $gateValidator,
            $alertDispatcher,
            $runRepository
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        ));

        // Macro failure triggers gate failure → overall Failed + alert
        $this->assertSame(CollectionStatus::Failed, $result->overallStatus);
        $this->assertCount(1, $alertNotifier->events);

        $run = $this->getRunRow();
        $this->assertSame(CollectionStatus::Failed->value, $run['status']);
        $this->assertSame(1, (int) $run['companies_total']);
        $this->assertSame(1, (int) $run['companies_success']);
        $this->assertSame(0, (int) $run['companies_failed']);
        $this->assertSame(0, (int) $run['gate_passed']);
        $this->assertSame(1, (int) $run['error_count']);
    }

    public function testCollectsCompaniesInBatches(): void
    {
        $config = $this->createIndustryConfig(['AAA', 'BBB', 'CCC']);
        $macroResult = new CollectMacroResult(
            data: new MacroData(),
            sourceAttempts: [],
            status: CollectionStatus::Complete,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $collectedTickers = [];
        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->expects($this->exactly(3))
            ->method('collect')
            ->willReturnCallback(function (CollectCompanyRequest $request) use (&$collectedTickers): CollectCompanyResult {
                $collectedTickers[] = $request->ticker;

                return $this->createCompanyResult($request->config, CollectionStatus::Complete);
            });

        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validateResults')->willReturn(new GateResult(true, [], []));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $runRepository = $this->createRunRepository();

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $gateValidator,
            $alertDispatcher,
            $runRepository
        );

        $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 2,
            enableMemoryManagement: false,
        ));

        $this->assertSame(['AAA', 'BBB', 'CCC'], $collectedTickers);

        $run = $this->getRunRow();
        $this->assertSame(3, (int) $run['companies_total']);
        $this->assertSame(3, (int) $run['companies_success']);
        $this->assertSame(0, (int) $run['companies_failed']);
    }

    public function testPeerCompaniesReceiveRelaxedRequirementsForFocalScopeMetrics(): void
    {
        $config = $this->createIndustryConfigWithFocalScope(['FOCAL', 'PEER1', 'PEER2'], ['FOCAL']);
        $macroResult = new CollectMacroResult(
            data: new MacroData(),
            sourceAttempts: [],
            status: CollectionStatus::Complete,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $capturedRequests = [];
        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->method('collect')
            ->willReturnCallback(function (CollectCompanyRequest $request) use (&$capturedRequests): CollectCompanyResult {
                $capturedRequests[$request->ticker] = $request;

                return $this->createCompanyResult($request->config, CollectionStatus::Complete);
            });

        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validateResults')->willReturn(new GateResult(true, [], []));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);
        $runRepository = $this->createRunRepository();

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $gateValidator,
            $alertDispatcher,
            $runRepository
        );

        $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 10,
            enableMemoryManagement: false,
        ));

        $this->assertCount(3, $capturedRequests);

        // Focal company should have fcf_yield as required (scope=focal means required for focal)
        $focalFcfYield = $this->findMetric($capturedRequests['FOCAL']->requirements->valuationMetrics, 'fcf_yield');
        $this->assertNotNull($focalFcfYield, 'fcf_yield metric should exist for focal');
        $this->assertTrue($focalFcfYield->required, 'fcf_yield should be required for focal company');

        // Peer companies should have fcf_yield as NOT required (scope=focal relaxes for peers)
        $peer1FcfYield = $this->findMetric($capturedRequests['PEER1']->requirements->valuationMetrics, 'fcf_yield');
        $this->assertNotNull($peer1FcfYield, 'fcf_yield metric should exist for peer');
        $this->assertFalse($peer1FcfYield->required, 'fcf_yield should NOT be required for peer company');

        $peer2FcfYield = $this->findMetric($capturedRequests['PEER2']->requirements->valuationMetrics, 'fcf_yield');
        $this->assertNotNull($peer2FcfYield, 'fcf_yield metric should exist for peer');
        $this->assertFalse($peer2FcfYield->required, 'fcf_yield should NOT be required for peer company');

        // market_cap with scope=all should remain required for all
        $peer1MarketCap = $this->findMetric($capturedRequests['PEER1']->requirements->valuationMetrics, 'market_cap');
        $this->assertNotNull($peer1MarketCap);
        $this->assertTrue($peer1MarketCap->required, 'market_cap (scope=all) should remain required for peers');
    }

    public function testDefaultsFirstCompanyAsFocalWhenNoneProvided(): void
    {
        $config = $this->createIndustryConfigWithFocalScope(['FIRST', 'PEER'], []);
        $macroResult = new CollectMacroResult(
            data: new MacroData(),
            sourceAttempts: [],
            status: CollectionStatus::Complete,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $capturedRequests = [];
        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->method('collect')
            ->willReturnCallback(function (CollectCompanyRequest $request) use (&$capturedRequests): CollectCompanyResult {
                $capturedRequests[$request->ticker] = $request;

                return $this->createCompanyResult($request->config, CollectionStatus::Complete);
            });

        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validateResults')->willReturn(new GateResult(true, [], []));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);
        $runRepository = $this->createRunRepository();

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $gateValidator,
            $alertDispatcher,
            $runRepository
        );

        $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 10,
            enableMemoryManagement: false,
        ));

        $this->assertCount(2, $capturedRequests);

        $firstFcfYield = $this->findMetric($capturedRequests['FIRST']->requirements->valuationMetrics, 'fcf_yield');
        $this->assertNotNull($firstFcfYield, 'fcf_yield should exist for fallback focal');
        $this->assertTrue($firstFcfYield->required, 'fcf_yield should be required for fallback focal');

        $peerFcfYield = $this->findMetric($capturedRequests['PEER']->requirements->valuationMetrics, 'fcf_yield');
        $this->assertNotNull($peerFcfYield, 'fcf_yield should exist for peer');
        $this->assertFalse($peerFcfYield->required, 'fcf_yield should NOT be required for peer');
    }

    /**
     * @param list<MetricDefinition> $metrics
     */
    private function findMetric(array $metrics, string $key): ?MetricDefinition
    {
        foreach ($metrics as $metric) {
            if ($metric->key === $key) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tickers
     * @param list<string> $focalTickers
     */
    private function createIndustryConfigWithFocalScope(array $tickers, array $focalTickers): IndustryConfig
    {
        $companies = array_map(
            fn (string $ticker): CompanyConfig => $this->createCompanyConfig($ticker),
            $tickers
        );

        return new IndustryConfig(
            id: 'energy',
            name: 'Energy',
            sector: 'Energy',
            companies: $companies,
            macroRequirements: new MacroRequirements(),
            dataRequirements: new DataRequirements(
                historyYears: 1,
                quartersToFetch: 4,
                valuationMetrics: [
                    new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true, MetricDefinition::SCOPE_ALL),
                    new MetricDefinition('fcf_yield', MetricDefinition::UNIT_PERCENT, true, MetricDefinition::SCOPE_FOCAL),
                ],
                annualFinancialMetrics: [],
                quarterMetrics: [],
                operationalMetrics: [],
            ),
            focalTickers: $focalTickers,
        );
    }

    /**
     * @param list<string> $tickers
     */
    private function createIndustryConfig(array $tickers): IndustryConfig
    {
        $companies = array_map(
            fn (string $ticker): CompanyConfig => $this->createCompanyConfig($ticker),
            $tickers
        );

        return new IndustryConfig(
            id: 'energy',
            name: 'Energy',
            sector: 'Energy',
            companies: $companies,
            macroRequirements: new MacroRequirements(),
            dataRequirements: new DataRequirements(
                historyYears: 1,
                quartersToFetch: 4,
                valuationMetrics: [
                    new MetricDefinition('market_cap', MetricDefinition::UNIT_CURRENCY, true),
                ],
                annualFinancialMetrics: [],
                quarterMetrics: [],
                operationalMetrics: [],
            ),
        );
    }

    private function createCompanyConfig(string $ticker): CompanyConfig
    {
        return new CompanyConfig(
            ticker: $ticker,
            name: $ticker . ' Corp',
            listingExchange: 'NYSE',
            listingCurrency: 'USD',
            reportingCurrency: 'USD',
            fyEndMonth: 12,
            alternativeTickers: null,
        );
    }

    private function createCompanyResult(
        CompanyConfig $config,
        CollectionStatus $status
    ): CollectCompanyResult {
        return new CollectCompanyResult(
            ticker: $config->ticker,
            data: $this->createCompanyData($config),
            sourceAttempts: [],
            status: $status,
        );
    }

    private function createCompanyData(CompanyConfig $config): CompanyData
    {
        return new CompanyData(
            ticker: $config->ticker,
            name: $config->name,
            listingExchange: $config->listingExchange,
            listingCurrency: $config->listingCurrency,
            reportingCurrency: $config->reportingCurrency,
            valuation: new ValuationData(
                marketCap: $this->createMoneyDatapoint(120.0),
            ),
            financials: new FinancialsData(
                historyYears: 1,
                annualData: [],
            ),
            quarters: new QuartersData(quarters: []),
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

    private function createHandler(
        CollectCompanyInterface $companyCollector,
        CollectMacroInterface $macroCollector,
        CollectionGateValidatorInterface $gateValidator,
        AlertDispatcher $alertDispatcher,
        CollectionRunRepository $runRepository
    ): CollectIndustryHandler {
        return new CollectIndustryHandler(
            companyCollector: $companyCollector,
            macroCollector: $macroCollector,
            gateValidator: $gateValidator,
            alertDispatcher: $alertDispatcher,
            runRepository: $runRepository,
            logger: $this->createMock(Logger::class),
        );
    }

    private function createRunRepository(): CollectionRunRepository
    {
        $db = new Connection(['dsn' => 'sqlite::memory:']);
        $db->open();
        $db->createCommand(
            'CREATE TABLE collection_run (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                industry_id TEXT NOT NULL,
                datapack_id TEXT NOT NULL,
                status TEXT NOT NULL,
                started_at TEXT NOT NULL,
                completed_at TEXT NULL,
                companies_total INTEGER NOT NULL DEFAULT 0,
                companies_success INTEGER NOT NULL DEFAULT 0,
                companies_failed INTEGER NOT NULL DEFAULT 0,
                gate_passed INTEGER NULL,
                error_count INTEGER NOT NULL DEFAULT 0,
                warning_count INTEGER NOT NULL DEFAULT 0,
                file_path TEXT NULL,
                file_size_bytes INTEGER NULL,
                duration_seconds INTEGER NULL
            )'
        )->execute();
        $db->createCommand(
            'CREATE TABLE collection_error (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_run_id INTEGER NOT NULL,
                severity TEXT NOT NULL,
                error_code TEXT NOT NULL,
                error_message TEXT NOT NULL,
                error_path TEXT NULL,
                ticker TEXT NULL
            )'
        )->execute();

        $this->runDb = $db;

        return new CollectionRunRepository($db);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRunRow(): array
    {
        $this->assertNotNull($this->runDb);
        $row = $this->runDb->createCommand('SELECT * FROM collection_run')->queryOne();
        $this->assertIsArray($row);

        return $row;
    }
}

final class TestAlertNotifier implements AlertNotifierInterface
{
    /**
     * @var CollectionAlertEvent[]
     */
    public array $events = [];

    public function notify(CollectionAlertEvent $event): void
    {
        $this->events[] = $event;
    }

    public function supports(string $severity): bool
    {
        return true;
    }
}
