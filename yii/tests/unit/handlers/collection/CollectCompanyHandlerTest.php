<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\dto\CollectCompanyRequest;
use app\dto\CollectDatapointRequest;
use app\dto\CollectDatapointResult;
use app\dto\CompanyConfig;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\dto\DataRequirements;
use app\dto\FetchResult;
use app\dto\HistoricalExtraction;
use app\dto\MetricDefinition;
use app\dto\PeriodValue;
use app\dto\SourceAttempt;
use app\enums\CollectionMethod;
use app\enums\CollectionStatus;
use app\enums\DataScale;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\collection\CollectDatapointInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\log\Logger;

final class CollectCompanyHandlerTest extends Unit
{
    private CollectDatapointInterface $datapointCollector;
    private SourceCandidateFactory $sourceCandidateFactory;
    private DataPointFactory $dataPointFactory;
    private Logger $logger;
    private CollectCompanyHandler $handler;

    protected function _before(): void
    {
        $this->datapointCollector = $this->createMock(CollectDatapointInterface::class);
        $this->sourceCandidateFactory = new SourceCandidateFactory();
        $this->dataPointFactory = new DataPointFactory();
        $this->logger = $this->createMock(Logger::class);

        $this->handler = new CollectCompanyHandler(
            $this->datapointCollector,
            $this->sourceCandidateFactory,
            $this->dataPointFactory,
            $this->logger,
        );
    }

    public function testFullSuccessScenario(): void
    {
        $request = $this->createRequest(['market_cap', 'fwd_pe'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(3_000_000_000_000)
                    ),
                    'valuation.fwd_pe' => $this->createFoundResult(
                        'valuation.fwd_pe',
                        $this->createRatioDatapoint(25.5)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertSame('AAPL', $result->ticker);
        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNotNull($result->data->valuation->marketCap->value);
        $this->assertNotNull($result->data->valuation->fwdPe);
        $this->assertSame(25.5, $result->data->valuation->fwdPe->value);
        $this->assertNotEmpty($result->sourceAttempts);
    }

    public function testPartialSuccessWithOptionalMetricsMissing(): void
    {
        $request = $this->createRequest(
            ['market_cap'],
            ['fwd_pe', 'trailing_pe']
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(3_000_000_000_000)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNotNull($result->data->valuation->marketCap->value);
        $this->assertNull($result->data->valuation->fwdPe);
    }

    public function testFailureWhenRequiredMetricsMissing(): void
    {
        $request = $this->createRequest(['market_cap', 'fwd_pe'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(3_000_000_000_000)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
    }

    public function testFailureWhenMarketCapMissing(): void
    {
        $request = $this->createRequest(['market_cap'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturn($this->createNotFoundResult('valuation.market_cap'));

        $result = $this->handler->collect($request);

        $this->assertSame(CollectionStatus::Failed, $result->status);
    }

    public function testTimeoutSkipsSubsequentMetrics(): void
    {
        $request = $this->createRequest(
            ['market_cap', 'fwd_pe', 'trailing_pe'],
            [],
            0
        );

        $collectCount = 0;
        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) use (&$collectCount) {
                $collectCount++;
                usleep(10000);
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(3_000_000_000_000)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
        $this->assertLessThan(3, $collectCount);
    }

    public function testAggregatesSourceAttemptsFromAllCalls(): void
    {
        $request = $this->createRequest(['market_cap', 'fwd_pe'], []);

        $attempt1 = new SourceAttempt(
            url: 'https://example.com/quote1',
            providerId: 'yahoo',
            attemptedAt: new DateTimeImmutable(),
            outcome: 'success',
            httpStatus: 200,
        );

        $attempt2 = new SourceAttempt(
            url: 'https://example.com/quote2',
            providerId: 'yahoo',
            attemptedAt: new DateTimeImmutable(),
            outcome: 'success',
            httpStatus: 200,
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) use ($attempt1, $attempt2) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => new CollectDatapointResult(
                        datapointKey: $req->datapointKey,
                        datapoint: $this->createMoneyDatapoint(3_000_000_000_000),
                        sourceAttempts: [$attempt1],
                        found: true,
                    ),
                    'valuation.fwd_pe' => new CollectDatapointResult(
                        datapointKey: $req->datapointKey,
                        datapoint: $this->createRatioDatapoint(25.5),
                        sourceAttempts: [$attempt2],
                        found: true,
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertCount(2, $result->sourceAttempts);
    }

    public function testCalculatesFcfYieldFromMarketCapAndFcf(): void
    {
        $request = $this->createRequest(['market_cap', 'free_cash_flow_ttm'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(1_000_000_000_000)
                    ),
                    'valuation.free_cash_flow_ttm' => $this->createFoundResult(
                        'valuation.free_cash_flow_ttm',
                        $this->createMoneyDatapoint(100_000_000_000)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertNotNull($result->data->valuation->fcfYield);
        $this->assertEqualsWithDelta(10.0, $result->data->valuation->fcfYield->value, 0.01);
        $this->assertSame(CollectionMethod::Derived, $result->data->valuation->fcfYield->method);
    }

    public function testKeepsNotFoundDatapointForRequiredFcfYield(): void
    {
        $request = $this->createRequest(['market_cap', 'fcf_yield'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(3_000_000_000_000)
                    ),
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertSame(CollectionStatus::Partial, $result->status);
        $this->assertNotNull($result->data->valuation->fcfYield);
        $this->assertSame(CollectionMethod::NotFound, $result->data->valuation->fcfYield->method);
        $this->assertNotEmpty($result->data->valuation->fcfYield->attemptedSources);
    }

    public function testCalculatesFcfYieldFromQuarterlyFreeCashFlowWhenDirectSourcesMissing(): void
    {
        $request = $this->createRequest(['market_cap', 'fcf_yield'], []);

        $historicalExtraction = new HistoricalExtraction(
            datapointKey: 'quarters.free_cash_flow',
            periods: [
                new PeriodValue(new DateTimeImmutable('2024-09-30'), 10_000_000_000.0),
                new PeriodValue(new DateTimeImmutable('2024-06-30'), 10_000_000_000.0),
                new PeriodValue(new DateTimeImmutable('2024-03-31'), 10_000_000_000.0),
                new PeriodValue(new DateTimeImmutable('2023-12-31'), 10_000_000_000.0),
            ],
            unit: MetricDefinition::UNIT_CURRENCY,
            currency: 'USD',
            scale: 'units',
            locator: SourceLocator::json('cashflowStatementHistoryQuarterly', 'freeCashFlow'),
        );

        $quartersResult = new CollectDatapointResult(
            datapointKey: 'quarters.free_cash_flow',
            datapoint: $this->createMoneyDatapoint(10_000_000_000.0),
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL?modules=cashflowStatementHistoryQuarterly',
                    providerId: 'yahoo_finance_quarters',
                    attemptedAt: new DateTimeImmutable(),
                    outcome: 'success',
                    httpStatus: 200,
                ),
            ],
            found: true,
            historicalExtraction: $historicalExtraction,
            fetchResult: new FetchResult(
                content: '{}',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL?modules=cashflowStatementHistoryQuarterly',
                finalUrl: 'https://query1.finance.yahoo.com/v10/finance/quoteSummary/AAPL?modules=cashflowStatementHistoryQuarterly',
                retrievedAt: new DateTimeImmutable(),
            ),
        );

        $this->datapointCollector
            ->method('collect')
            ->willReturnCallback(function (CollectDatapointRequest $req) use ($quartersResult) {
                return match ($req->datapointKey) {
                    'valuation.market_cap' => $this->createFoundResult(
                        'valuation.market_cap',
                        $this->createMoneyDatapoint(400_000_000_000.0)
                    ),
                    'valuation.fcf_yield' => $this->createNotFoundResult($req->datapointKey),
                    'quarters.free_cash_flow' => $quartersResult,
                    default => $this->createNotFoundResult($req->datapointKey),
                };
            });

        $result = $this->handler->collect($request);

        $this->assertNotNull($result->data->valuation->freeCashFlowTtm);
        $this->assertSame(CollectionMethod::Derived, $result->data->valuation->freeCashFlowTtm->method);
        $this->assertSame(40_000_000_000.0, $result->data->valuation->freeCashFlowTtm->value);

        $this->assertNotNull($result->data->valuation->fcfYield);
        $this->assertSame(CollectionMethod::Derived, $result->data->valuation->fcfYield->method);
        $this->assertEqualsWithDelta(10.0, $result->data->valuation->fcfYield->value, 0.01);
    }

    public function testCompanyDataContainsCorrectMetadata(): void
    {
        $request = $this->createRequest(['market_cap'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturn($this->createFoundResult(
                'valuation.market_cap',
                $this->createMoneyDatapoint(3_000_000_000_000)
            ));

        $result = $this->handler->collect($request);

        $this->assertSame('AAPL', $result->data->ticker);
        $this->assertSame('Apple Inc.', $result->data->name);
        $this->assertSame('NASDAQ', $result->data->listingExchange);
        $this->assertSame('USD', $result->data->listingCurrency);
        $this->assertSame('USD', $result->data->reportingCurrency);
    }

    public function testFinancialsAndQuartersAreEmptyByDefault(): void
    {
        $request = $this->createRequest(['market_cap'], []);

        $this->datapointCollector
            ->method('collect')
            ->willReturn($this->createFoundResult(
                'valuation.market_cap',
                $this->createMoneyDatapoint(3_000_000_000_000)
            ));

        $result = $this->handler->collect($request);

        $this->assertEmpty($result->data->financials->annualData);
        $this->assertEmpty($result->data->quarters->quarters);
    }

    /**
     * @param list<string> $requiredMetrics
     * @param list<string> $optionalMetrics
     */
    private function createRequest(
        array $requiredMetrics,
        array $optionalMetrics,
        int $maxDurationSeconds = 120
    ): CollectCompanyRequest {
        $definitions = [];
        foreach ($requiredMetrics as $metric) {
            $definitions[] = new MetricDefinition($metric, $this->unitForMetric($metric), true);
        }
        foreach ($optionalMetrics as $metric) {
            $definitions[] = new MetricDefinition($metric, $this->unitForMetric($metric), false);
        }

        return new CollectCompanyRequest(
            ticker: 'AAPL',
            config: new CompanyConfig(
                ticker: 'AAPL',
                name: 'Apple Inc.',
                listingExchange: 'NASDAQ',
                listingCurrency: 'USD',
                reportingCurrency: 'USD',
                fyEndMonth: 9,
            ),
            requirements: new DataRequirements(
                historyYears: 5,
                quartersToFetch: 4,
                valuationMetrics: $definitions,
                annualFinancialMetrics: [],
                quarterMetrics: [],
                operationalMetrics: [],
            ),
            maxDurationSeconds: $maxDurationSeconds,
        );
    }

    private function unitForMetric(string $metric): string
    {
        return match ($metric) {
            'market_cap', 'free_cash_flow_ttm' => MetricDefinition::UNIT_CURRENCY,
            'fcf_yield', 'div_yield' => MetricDefinition::UNIT_PERCENT,
            default => MetricDefinition::UNIT_RATIO,
        };
    }

    private function createMoneyDatapoint(float $value): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="MARKET_CAP-value"]', (string) $value),
        );
    }

    private function createRatioDatapoint(float $value): DataPointRatio
    {
        return new DataPointRatio(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://finance.yahoo.com/quote/AAPL',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('td[data-test="PE_RATIO-value"]', (string) $value),
        );
    }

    private function createFoundResult(
        string $datapointKey,
        DataPointMoney|DataPointRatio|DataPointPercent $datapoint
    ): CollectDatapointResult {
        return new CollectDatapointResult(
            datapointKey: $datapointKey,
            datapoint: $datapoint,
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://finance.yahoo.com/quote/AAPL',
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
        $metric = explode('.', $datapointKey, 2)[1] ?? $datapointKey;
        $unit = $this->unitForMetric($metric);

        return new CollectDatapointResult(
            datapointKey: $datapointKey,
            datapoint: $this->dataPointFactory->notFound($unit, ['https://example.com (not found)']),
            sourceAttempts: [
                new SourceAttempt(
                    url: 'https://finance.yahoo.com/quote/AAPL',
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
}
