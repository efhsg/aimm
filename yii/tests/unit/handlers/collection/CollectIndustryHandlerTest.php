<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\alerts\AlertDispatcher;
use app\alerts\AlertNotifierInterface;
use app\alerts\CollectionAlertEvent;
use app\dto\CollectCompanyRequest;
use app\dto\CollectCompanyResult;
use app\dto\CollectIndustryRequest;
use app\dto\CollectionLog;
use app\dto\CollectMacroResult;
use app\dto\CompanyConfig;
use app\dto\CompanyData;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\SourceLocator;
use app\dto\DataRequirements;
use app\dto\FinancialsData;
use app\dto\GateError;
use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\dto\IndustryDataPack;
use app\dto\MacroData;
use app\dto\MacroRequirements;
use app\dto\QuartersData;
use app\dto\ValuationData;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\handlers\collection\CollectCompanyInterface;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectMacroInterface;
use app\queries\DataPackRepository;
use app\transformers\DataPackAssemblerInterface;
use app\validators\CollectionGateValidatorInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\log\Logger;

final class CollectIndustryHandlerTest extends Unit
{
    private string $tempDir;
    private DataPackRepository $repository;

    protected function _before(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/collect-industry-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->repository = new DataPackRepository($this->tempDir);
    }

    protected function _after(): void
    {
        $this->deleteDirectory($this->tempDir);
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

        $assembler = new TestDataPackAssembler($this->repository);

        $gateResult = new GateResult(true, [], []);
        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validate')->willReturn($gateResult);

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $this->repository,
            $assembler,
            $gateValidator,
            $alertDispatcher
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        ));

        $this->assertSame($config->id, $result->industryId);
        $this->assertNotSame('', $result->datapackId);
        $this->assertStringContainsString('datapack.json', $result->dataPackPath);
        $this->assertSame(CollectionStatus::Complete, $result->overallStatus);
        $this->assertSame($gateResult, $result->gateResult);
        $this->assertCount(2, $result->companyStatuses);
        $this->assertSame(CollectionStatus::Complete, $result->companyStatuses['AAA']);
        $this->assertSame(CollectionStatus::Complete, $result->companyStatuses['BBB']);
        $this->assertSame(
            ['AAA', 'BBB'],
            $this->repository->listIntermediateTickers($config->id, $result->datapackId)
        );
        $this->assertSame([], $alertNotifier->events);
    }

    public function testFailsOverallWhenMacroFails(): void
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

        $assembler = new TestDataPackAssembler($this->repository);

        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validate')->willReturn(new GateResult(true, [], []));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $this->repository,
            $assembler,
            $gateValidator,
            $alertDispatcher
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        ));

        $this->assertSame(CollectionStatus::Failed, $result->overallStatus);
        $this->assertSame([], $alertNotifier->events);
    }

    public function testPersistsIntermediatesInBatches(): void
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

        $assembler = new TestDataPackAssembler($this->repository);

        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validate')->willReturn(new GateResult(true, [], []));

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $this->repository,
            $assembler,
            $gateValidator,
            $alertDispatcher
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 2,
            enableMemoryManagement: false,
        ));

        $this->assertSame(['AAA', 'BBB', 'CCC'], $collectedTickers);
        $this->assertSame(
            ['AAA', 'BBB', 'CCC'],
            $this->repository->listIntermediateTickers($config->id, $result->datapackId)
        );
    }

    public function testAlertsWhenGateFails(): void
    {
        $config = $this->createIndustryConfig(['AAA']);
        $macroResult = new CollectMacroResult(
            data: new MacroData(),
            sourceAttempts: [],
            status: CollectionStatus::Complete,
        );

        $macroCollector = $this->createMock(CollectMacroInterface::class);
        $macroCollector->method('collect')->willReturn($macroResult);

        $companyCollector = $this->createMock(CollectCompanyInterface::class);
        $companyCollector->method('collect')
            ->willReturn($this->createCompanyResult(
                $config->companies[0],
                CollectionStatus::Complete
            ));

        $assembler = new TestDataPackAssembler($this->repository);

        $gateErrors = [new GateError('GATE_FAILED', 'Gate failed')];
        $gateResult = new GateResult(false, $gateErrors, []);
        $gateValidator = $this->createMock(CollectionGateValidatorInterface::class);
        $gateValidator->method('validate')->willReturn($gateResult);

        $alertNotifier = new TestAlertNotifier();
        $alertDispatcher = new AlertDispatcher([$alertNotifier]);

        $handler = $this->createHandler(
            $companyCollector,
            $macroCollector,
            $this->repository,
            $assembler,
            $gateValidator,
            $alertDispatcher
        );

        $result = $handler->collect(new CollectIndustryRequest(
            config: $config,
            batchSize: 1,
            enableMemoryManagement: false,
        ));

        $this->assertSame(CollectionStatus::Failed, $result->overallStatus);
        $this->assertCount(1, $alertNotifier->events);
        $event = $alertNotifier->events[0];
        $this->assertSame(CollectionAlertEvent::SEVERITY_WARNING, $event->severity);
        $this->assertSame('GATE_FAILED', $event->type);
        $this->assertSame($config->id, $event->context['industry_id'] ?? null);
        $this->assertSame($result->datapackId, $event->context['datapack_id'] ?? null);
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
                requiredValuationMetrics: ['market_cap'],
                optionalValuationMetrics: [],
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
        DataPackRepository $repository,
        DataPackAssemblerInterface $assembler,
        CollectionGateValidatorInterface $gateValidator,
        AlertDispatcher $alertDispatcher
    ): CollectIndustryHandler {
        return new CollectIndustryHandler(
            companyCollector: $companyCollector,
            macroCollector: $macroCollector,
            repository: $repository,
            assembler: $assembler,
            gateValidator: $gateValidator,
            alertDispatcher: $alertDispatcher,
            logger: $this->createMock(Logger::class),
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

final class TestDataPackAssembler implements DataPackAssemblerInterface
{
    public function __construct(
        private DataPackRepository $repository,
    ) {
    }

    public function assemble(
        string $industryId,
        string $datapackId,
        MacroData $macro,
        CollectionLog $collectionLog,
        DateTimeImmutable $collectedAt,
    ): string {
        $companies = [];
        foreach ($this->repository->listIntermediateTickers($industryId, $datapackId) as $ticker) {
            $company = $this->repository->loadCompanyIntermediate($industryId, $datapackId, $ticker);
            if ($company !== null) {
                $companies[$ticker] = $company;
            }
        }

        $dataPack = new IndustryDataPack(
            industryId: $industryId,
            datapackId: $datapackId,
            collectedAt: $collectedAt,
            macro: $macro,
            companies: $companies,
            collectionLog: $collectionLog,
        );

        return $this->repository->save($dataPack);
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
