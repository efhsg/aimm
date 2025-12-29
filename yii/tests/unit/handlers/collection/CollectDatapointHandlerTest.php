<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\adapters\SourceAdapterInterface;
use app\clients\FetchRequest;
use app\clients\WebFetchClientInterface;
use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\dto\CollectDatapointRequest;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\SourceCandidate;
use app\enums\CollectionMethod;
use app\enums\Severity;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\exceptions\RateLimitException;
use app\factories\DataPointFactory;
use app\handlers\collection\CollectDatapointHandler;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\log\Logger;

final class CollectDatapointHandlerTest extends Unit
{
    private WebFetchClientInterface $webFetchClient;
    private SourceAdapterInterface $sourceAdapter;
    private DataPointFactory $dataPointFactory;
    private Logger $logger;
    private CollectDatapointHandler $handler;

    protected function _before(): void
    {
        $this->webFetchClient = $this->createMock(WebFetchClientInterface::class);
        $this->sourceAdapter = $this->createMock(SourceAdapterInterface::class);
        $this->dataPointFactory = new DataPointFactory();
        $this->logger = $this->createMock(Logger::class);

        $this->handler = new CollectDatapointHandler(
            $this->webFetchClient,
            $this->sourceAdapter,
            $this->dataPointFactory,
            $this->logger,
        );
    }

    public function testSucceedsOnFirstCandidate(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate]);

        $fetchResult = $this->createFetchResult('https://example.com/quote');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'yahoo',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertSame('valuation.fwd_pe', $result->datapointKey);
        $this->assertSame(25.5, $result->datapoint->value);
        $this->assertSame(CollectionMethod::WebFetch, $result->datapoint->method);
        $this->assertCount(1, $result->sourceAttempts);
        $this->assertSame('success', $result->sourceAttempts[0]->outcome);
    }

    public function testFallsBackToSecondCandidateWhenFirstFails(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult1 = $this->createFetchResult('https://first.com/quote', 500);
        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($fetchResult1, $fetchResult2);

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'second',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('http_error', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testFallsBackOnNetworkException(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnCallback(function (FetchRequest $req) use ($fetchResult2) {
                if ($req->url === 'https://first.com/quote') {
                    throw new NetworkException('Connection failed', $req->url);
                }
                return $fetchResult2;
            });

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'second',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('network_error', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testFallsBackOnBlockedException(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnCallback(function (FetchRequest $req) use ($fetchResult2) {
                if ($req->url === 'https://first.com/quote') {
                    throw new BlockedException('Bot detected', 'first.com', $req->url);
                }
                return $fetchResult2;
            });

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'second',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('blocked', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testFallsBackOnRateLimitException(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnCallback(function (FetchRequest $req) use ($fetchResult2) {
                if ($req->url === 'https://first.com/quote') {
                    throw new RateLimitException('Rate limited', 'first.com');
                }
                return $fetchResult2;
            });

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'second',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('rate_limited', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testReturnsNotFoundWhenAllSourcesExhausted(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult1 = $this->createFetchResult('https://first.com/quote', 404);
        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($fetchResult1, $fetchResult2);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'second',
                extractions: [],
                notFound: ['valuation.fwd_pe'],
            ));

        $result = $this->handler->collect($request);

        $this->assertFalse($result->found);
        $this->assertNull($result->datapoint->value);
        $this->assertSame(CollectionMethod::NotFound, $result->datapoint->method);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('http_error', $result->sourceAttempts[0]->outcome);
        $this->assertSame('not_in_page', $result->sourceAttempts[1]->outcome);
        $this->assertNotEmpty($result->datapoint->attemptedSources);
    }

    public function testNotFoundUsesUnitOverride(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'example', 1);
        $request = $this->createRequest('valuation.custom_metric', [$candidate], null, 'percent');

        $fetchResult = $this->createFetchResult('https://example.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'example',
                extractions: [],
                notFound: ['valuation.custom_metric'],
            ));

        $result = $this->handler->collect($request);

        $this->assertFalse($result->found);
        $this->assertSame('percent', $result->datapoint::UNIT);
    }

    public function testRejectsStaleData(): void
    {
        $candidate1 = $this->createCandidate('https://first.com/quote', 'first', 1);
        $candidate2 = $this->createCandidate('https://second.com/quote', 'second', 2);

        $asOfMin = new DateTimeImmutable('2024-01-15');
        $request = $this->createRequest(
            'valuation.fwd_pe',
            [$candidate1, $candidate2],
            $asOfMin
        );

        $fetchResult1 = $this->createFetchResult('https://first.com/quote', 200);
        $fetchResult2 = $this->createFetchResult('https://second.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($fetchResult1, $fetchResult2);

        $staleExtraction = $this->createExtraction(
            'valuation.fwd_pe',
            25.5,
            new DateTimeImmutable('2024-01-01')
        );
        $freshExtraction = $this->createExtraction(
            'valuation.fwd_pe',
            26.0,
            new DateTimeImmutable('2024-01-20')
        );

        $this->sourceAdapter
            ->method('adapt')
            ->willReturnOnConsecutiveCalls(
                new AdaptResult(
                    adapterId: 'first',
                    extractions: ['valuation.fwd_pe' => $staleExtraction],
                    notFound: [],
                ),
                new AdaptResult(
                    adapterId: 'second',
                    extractions: ['valuation.fwd_pe' => $freshExtraction],
                    notFound: [],
                )
            );

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertSame(26.0, $result->datapoint->value);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('stale', $result->sourceAttempts[0]->outcome);
        $this->assertStringContainsString('older than required', $result->sourceAttempts[0]->reason);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testSkipsRateLimitedDomain(): void
    {
        $candidate1 = $this->createCandidate('https://limited.com/quote', 'limited', 1);
        $candidate2 = $this->createCandidate('https://available.com/quote', 'available', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult = $this->createFetchResult('https://available.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturnCallback(fn (string $domain) => $domain === 'limited.com');

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'available',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('rate_limited', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testRecordsHttpStatusInAttempt(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'example', 1);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate]);

        $fetchResult = $this->createFetchResult('https://example.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'example',
                extractions: ['valuation.fwd_pe' => $extraction],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertSame(200, $result->sourceAttempts[0]->httpStatus);
    }

    public function testHandlesParseFailure(): void
    {
        $candidate1 = $this->createCandidate('https://broken.com/quote', 'broken', 1);
        $candidate2 = $this->createCandidate('https://working.com/quote', 'working', 2);
        $request = $this->createRequest('valuation.fwd_pe', [$candidate1, $candidate2]);

        $fetchResult1 = $this->createFetchResult('https://broken.com/quote', 200);
        $fetchResult2 = $this->createFetchResult('https://working.com/quote', 200);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturnOnConsecutiveCalls($fetchResult1, $fetchResult2);

        $extraction = $this->createExtraction('valuation.fwd_pe', 25.5);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturnCallback(function (AdaptRequest $req) use ($extraction) {
                if ($req->fetchResult->url === 'https://broken.com/quote') {
                    throw new \RuntimeException('Parse error: invalid HTML');
                }
                return new AdaptResult(
                    adapterId: 'working',
                    extractions: ['valuation.fwd_pe' => $extraction],
                    notFound: [],
                );
            });

        $result = $this->handler->collect($request);

        $this->assertTrue($result->found);
        $this->assertCount(2, $result->sourceAttempts);
        $this->assertSame('parse_failed', $result->sourceAttempts[0]->outcome);
        $this->assertSame('success', $result->sourceAttempts[1]->outcome);
    }

    public function testInfersUnitFromDatapointKey(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'example', 1);

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $fetchResult = $this->createFetchResult('https://example.com/quote', 200);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'example',
                extractions: [],
                notFound: ['valuation.market_cap'],
            ));

        $request = $this->createRequest('valuation.market_cap', [$candidate]);
        $result = $this->handler->collect($request);

        $this->assertFalse($result->found);
        $this->assertSame('currency', $result->datapoint::UNIT);
    }

    /**
     * @param SourceCandidate[] $candidates
     */
    private function createRequest(
        string $datapointKey,
        array $candidates,
        ?DateTimeImmutable $asOfMin = null,
        ?string $unit = null
    ): CollectDatapointRequest {
        return new CollectDatapointRequest(
            datapointKey: $datapointKey,
            sourceCandidates: $candidates,
            adapterId: 'test',
            severity: Severity::Required,
            ticker: 'AAPL',
            asOfMin: $asOfMin,
            unit: $unit,
        );
    }

    private function createCandidate(string $url, string $adapterId, int $priority): SourceCandidate
    {
        $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';

        return new SourceCandidate(
            url: $url,
            adapterId: $adapterId,
            priority: $priority,
            domain: $domain,
        );
    }

    private function createFetchResult(string $url, int $statusCode = 200): FetchResult
    {
        return new FetchResult(
            content: '<html><body>Test content</body></html>',
            contentType: 'text/html',
            statusCode: $statusCode,
            url: $url,
            finalUrl: $url,
            retrievedAt: new DateTimeImmutable(),
        );
    }

    private function createExtraction(
        string $datapointKey,
        float $value,
        ?DateTimeImmutable $asOf = null
    ): Extraction {
        return new Extraction(
            datapointKey: $datapointKey,
            rawValue: $value,
            unit: 'ratio',
            currency: null,
            scale: null,
            asOf: $asOf,
            locator: SourceLocator::html('td[data-test="PE_RATIO-value"]', (string)$value),
        );
    }
}
