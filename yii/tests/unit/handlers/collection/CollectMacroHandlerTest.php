<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\dto\CollectBatchResult;
use app\dto\CollectDatapointRequest;
use app\dto\CollectDatapointResult;
use app\dto\CollectMacroRequest;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\MacroRequirements;
use app\dto\SourceAttempt;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use app\handlers\collection\CollectBatchInterface;
use app\handlers\collection\CollectDatapointInterface;
use app\handlers\collection\CollectMacroHandler;
use app\queries\MacroIndicatorQuery;
use app\queries\PriceHistoryQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\log\Logger;

final class CollectMacroHandlerTest extends Unit
{
    private CollectDatapointInterface $datapointCollector;
    private CollectBatchInterface $batchCollector;
    private SourceCandidateFactory $sourceCandidateFactory;
    private DataPointFactory $dataPointFactory;
    private Logger $logger;
    private MacroIndicatorQuery $macroQuery;
    private PriceHistoryQuery $priceQuery;

    protected function _before(): void
    {
        $this->datapointCollector = $this->createMock(CollectDatapointInterface::class);
        $this->sourceCandidateFactory = new SourceCandidateFactory();
        $this->dataPointFactory = new DataPointFactory();
        $this->logger = $this->createMock(Logger::class);
        $this->macroQuery = $this->createMock(MacroIndicatorQuery::class);
        $this->priceQuery = $this->createMock(PriceHistoryQuery::class);
    }

    private function createHandler(?CollectBatchInterface $batchCollector = null): CollectMacroHandler
    {
        $this->batchCollector = $batchCollector ?? $this->createEmptyBatchCollector();

        return new CollectMacroHandler(
            $this->datapointCollector,
            $this->batchCollector,
            $this->sourceCandidateFactory,
            $this->dataPointFactory,
            $this->logger,
            $this->macroQuery,
            $this->priceQuery,
        );
    }

    private function createEmptyBatchCollector(): CollectBatchInterface
    {
        $mock = $this->createMock(CollectBatchInterface::class);
        $mock->method('collect')
            ->willReturn(new CollectBatchResult(
                found: [],
                notFound: [],
                historicalFound: [],
                sourceAttempts: [],
                requiredSatisfied: true,
            ));
        return $mock;
    }

    public function testFullSuccessWithAllMacroTypes(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
                marginProxy: 'macro.gas_price',
                sectorIndex: 'macro.sp500',
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'macro.commodity_benchmark' => $this->createFoundMoneyResult(
                        $req->datapointKey,
                        75.50
                    ),
                    'macro.margin_proxy' => $this->createFoundMoneyResult(
                        $req->datapointKey,
                        2.85
                    ),
                    'macro.sector_index' => $this->createFoundNumberResult(
                        $req->datapointKey,
                        4500.25
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNotNull($result->data->commodityBenchmark);
        $this->assertSame(75.50, $result->data->commodityBenchmark->value);
        $this->assertNotNull($result->data->marginProxy);
        $this->assertSame(2.85, $result->data->marginProxy->value);
        $this->assertNotNull($result->data->sectorIndex);
        $this->assertSame(4500.25, $result->data->sectorIndex->value);
        $this->assertNotEmpty($result->sourceAttempts);
    }

    public function testPartialSuccessWithOptionalIndicatorsMissing(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
                requiredIndicators: [],
                optionalIndicators: ['macro.gold_price'],
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'macro.commodity_benchmark' => $this->createFoundMoneyResult(
                        $req->datapointKey,
                        75.50
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
        $this->assertNotNull($result->data->commodityBenchmark);
        $this->assertEmpty($result->data->additionalIndicators);
    }

    public function testCompleteWhenOnlyOptionalIndicatorsMissing(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                optionalIndicators: ['macro.gold_price'],
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return $this->createNotFoundResult($req->datapointKey);
            });

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertEmpty($result->data->additionalIndicators);
    }

    public function testFailureWhenRequiredCommodityBenchmarkMissing(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturn($this->createNotFoundResult('macro.commodity_benchmark'));

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Failed, $result->status);
        $this->assertNull($result->data->commodityBenchmark);
    }

    public function testPartialWhenRequiredIndicatorMissingButHasSomeData(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
                requiredIndicators: ['macro.gold_price'],
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'macro.commodity_benchmark' => $this->createFoundMoneyResult(
                        $req->datapointKey,
                        75.50
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        // Batch collector returns not found for the required indicator
        $batchCollector = $this->createMock(CollectBatchInterface::class);
        $batchCollector->method('collect')
            ->willReturn(new CollectBatchResult(
                found: [],
                notFound: ['macro.gold_price'],
                historicalFound: [],
                sourceAttempts: [],
                requiredSatisfied: false,
            ));

        $handler = $this->createHandler($batchCollector);
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
        $this->assertNotNull($result->data->commodityBenchmark);
        $this->assertEmpty($result->data->additionalIndicators);
    }

    public function testCompleteWhenNoRequirementsDefined(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(),
        );

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNull($result->data->commodityBenchmark);
        $this->assertNull($result->data->marginProxy);
        $this->assertNull($result->data->sectorIndex);
        $this->assertEmpty($result->data->additionalIndicators);
        $this->assertEmpty($result->sourceAttempts);
    }

    public function testAggregatesSourceAttemptsFromAllCalls(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
                marginProxy: 'macro.gas_price',
            ),
        );

        $attempt1 = new SourceAttempt(
            url: 'https://example.com/oil',
            providerId: 'yahoo',
            attemptedAt: new DateTimeImmutable(),
            outcome: 'success',
            httpStatus: 200,
        );

        $attempt2 = new SourceAttempt(
            url: 'https://example.com/gas',
            providerId: 'yahoo',
            attemptedAt: new DateTimeImmutable(),
            outcome: 'success',
            httpStatus: 200,
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) use ($attempt1, $attempt2) {
                return match ($req->datapointKey) {
                    'macro.commodity_benchmark' => new CollectDatapointResult(
                        datapointKey: $req->datapointKey,
                        datapoint: $this->createMoneyDatapoint(75.50),
                        sourceAttempts: [$attempt1],
                        found: true,
                    ),
                    'macro.margin_proxy' => new CollectDatapointResult(
                        datapointKey: $req->datapointKey,
                        datapoint: $this->createMoneyDatapoint(2.85),
                        sourceAttempts: [$attempt2],
                        found: true,
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertCount(2, $result->sourceAttempts);
    }

    public function testCollectsRequiredAndOptionalIndicators(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                requiredIndicators: ['macro.oil_price'],
                optionalIndicators: ['macro.gold_price'],
            ),
        );

        // Configure batch collector to return found extractions
        $batchCollector = $this->createMock(CollectBatchInterface::class);
        $batchCollector->method('collect')
            ->willReturn(new CollectBatchResult(
                found: [
                    'macro.oil_price' => $this->createExtraction('macro.oil_price', 75.50),
                    'macro.gold_price' => $this->createExtraction('macro.gold_price', 1850.00),
                ],
                notFound: [],
                historicalFound: [],
                sourceAttempts: [],
                requiredSatisfied: true,
            ));

        $handler = $this->createHandler($batchCollector);
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertCount(2, $result->data->additionalIndicators);
        $this->assertArrayHasKey('macro.oil_price', $result->data->additionalIndicators);
        $this->assertArrayHasKey('macro.gold_price', $result->data->additionalIndicators);
    }

    public function testPartialWhenSectorIndexMissingButRequiredPresent(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.oil_price',
                sectorIndex: 'macro.sp500',
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'macro.commodity_benchmark' => $this->createFoundMoneyResult(
                        $req->datapointKey,
                        75.50
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
        $this->assertNotNull($result->data->commodityBenchmark);
        $this->assertNull($result->data->sectorIndex);
    }

    public function testHandlesUnknownMacroKeyGracefully(): void
    {
        $request = new CollectMacroRequest(
            requirements: new MacroRequirements(
                commodityBenchmark: 'macro.unknown_commodity',
            ),
        );

        $handler = $this->createHandler();
        $result = $handler->collect($request);

        $this->assertSame(CollectionStatus::Failed, $result->status);
        $this->assertNull($result->data->commodityBenchmark);
    }

    private function createMoneyDatapoint(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://finance.yahoo.com/quote/CL=F',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="price"]', (string) $value),
        );
    }

    private function createNumberDatapoint(float $value): DataPointNumber
    {
        return new DataPointNumber(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://finance.yahoo.com/quote/^GSPC',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="price"]', (string) $value),
        );
    }

    private function createFoundMoneyResult(string $datapointKey, float $value): CollectDatapointResult
    {
        return new CollectDatapointResult(
            datapointKey: $datapointKey,
            datapoint: $this->createMoneyDatapoint($value),
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://finance.yahoo.com/quote/CL=F',
                    providerId: 'yahoo_finance',
                    attemptedAt: new DateTimeImmutable(),
                    outcome: 'success',
                    httpStatus: 200,
                ),
            ],
            found: true,
        );
    }

    private function createFoundNumberResult(string $datapointKey, float $value): CollectDatapointResult
    {
        return new CollectDatapointResult(
            datapointKey: $datapointKey,
            datapoint: $this->createNumberDatapoint($value),
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://finance.yahoo.com/quote/^GSPC',
                    providerId: 'yahoo_finance',
                    attemptedAt: new DateTimeImmutable(),
                    outcome: 'success',
                    httpStatus: 200,
                ),
            ],
            found: true,
        );
    }

    private function createNotFoundResult(string $datapointKey): CollectDatapointResult
    {
        return new CollectDatapointResult(
            datapointKey: $datapointKey,
            datapoint: $this->dataPointFactory->notFound('currency', ['https://example.com (not found)']),
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://finance.yahoo.com/quote/CL=F',
                    providerId: 'yahoo_finance',
                    attemptedAt: new DateTimeImmutable(),
                    outcome: 'not_in_page',
                    reason: 'Value not found',
                    httpStatus: 200,
                ),
            ],
            found: false,
        );
    }

    private function createExtraction(string $key, float $value): Extraction
    {
        return new Extraction(
            datapointKey: $key,
            rawValue: $value,
            unit: 'currency',
            currency: 'USD',
            scale: 'units',
            asOf: new DateTimeImmutable(),
            locator: SourceLocator::html('td.value', (string) $value),
        );
    }
}
