<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\adapters\SourceAdapterInterface;
use app\clients\FetchRequest;
use app\clients\WebFetchClientInterface;
use app\dto\AdaptResult;
use app\dto\CollectBatchRequest;
use app\dto\datapoints\SourceLocator;
use app\dto\Extraction;
use app\dto\FetchResult;
use app\dto\HistoricalExtraction;
use app\dto\PeriodValue;
use app\dto\SourceCandidate;
use app\enums\SourceLocatorType;
use app\exceptions\BlockedException;
use app\exceptions\NetworkException;
use app\handlers\collection\CollectBatchHandler;
use Codeception\Test\Unit;
use DateTimeImmutable;
use yii\log\Logger;

final class CollectBatchHandlerTest extends Unit
{
    private WebFetchClientInterface $webFetchClient;
    private SourceAdapterInterface $sourceAdapter;
    private Logger $logger;
    private CollectBatchHandler $handler;

    protected function _before(): void
    {
        $this->webFetchClient = $this->createMock(WebFetchClientInterface::class);
        $this->sourceAdapter = $this->createMock(SourceAdapterInterface::class);
        $this->logger = $this->createMock(Logger::class);

        $this->handler = new CollectBatchHandler(
            $this->webFetchClient,
            $this->sourceAdapter,
            $this->logger,
        );
    }

    public function testCollectsAllRequestedKeysSuccessfully(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap', 'valuation.fwd_pe'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $fetchResult = $this->createFetchResult('https://example.com/quote');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'yahoo',
                extractions: [
                    'valuation.market_cap' => $this->createExtraction('valuation.market_cap', 3_000_000_000_000.0, 'currency'),
                    'valuation.fwd_pe' => $this->createExtraction('valuation.fwd_pe', 25.5, 'ratio'),
                ],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertCount(2, $result->found);
        $this->assertArrayHasKey('valuation.market_cap', $result->found);
        $this->assertArrayHasKey('valuation.fwd_pe', $result->found);
        $this->assertEmpty($result->notFound);
        $this->assertTrue($result->requiredSatisfied);
    }

    public function testReportsPartialSuccessWhenSomeKeysNotFound(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap', 'valuation.fwd_pe', 'valuation.trailing_pe'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $fetchResult = $this->createFetchResult('https://example.com/quote');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'yahoo',
                extractions: [
                    'valuation.market_cap' => $this->createExtraction('valuation.market_cap', 3_000_000_000_000.0, 'currency'),
                ],
                notFound: ['valuation.fwd_pe', 'valuation.trailing_pe'],
            ));

        $result = $this->handler->collect($request);

        $this->assertCount(1, $result->found);
        $this->assertArrayHasKey('valuation.market_cap', $result->found);
        $this->assertContains('valuation.fwd_pe', $result->notFound);
        $this->assertContains('valuation.trailing_pe', $result->notFound);
        $this->assertTrue($result->requiredSatisfied);
    }

    public function testRequiredNotSatisfiedWhenRequiredKeyMissing(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap', 'valuation.fwd_pe'],
            requiredKeys: ['valuation.market_cap', 'valuation.fwd_pe'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $fetchResult = $this->createFetchResult('https://example.com/quote');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'yahoo',
                extractions: [
                    'valuation.market_cap' => $this->createExtraction('valuation.market_cap', 3_000_000_000_000.0, 'currency'),
                ],
                notFound: ['valuation.fwd_pe'],
            ));

        $result = $this->handler->collect($request);

        $this->assertFalse($result->requiredSatisfied);
        $this->assertContains('valuation.fwd_pe', $result->notFound);
    }

    public function testSkipsRateLimitedSources(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(true);

        $result = $this->handler->collect($request);

        $this->assertEmpty($result->found);
        $this->assertContains('valuation.market_cap', $result->notFound);
        $this->assertFalse($result->requiredSatisfied);
        $this->assertNotEmpty($result->sourceAttempts);
        $this->assertSame('rate_limited', $result->sourceAttempts[0]->outcome);
    }

    public function testHandlesNetworkException(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willThrowException(new NetworkException('Connection failed', 'https://example.com/quote'));

        $result = $this->handler->collect($request);

        $this->assertEmpty($result->found);
        $this->assertFalse($result->requiredSatisfied);
        $this->assertSame('network_error', $result->sourceAttempts[0]->outcome);
    }

    public function testHandlesBlockedException(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willThrowException(new BlockedException('IP blocked', 'example.com', 'https://example.com/quote'));

        $result = $this->handler->collect($request);

        $this->assertEmpty($result->found);
        $this->assertFalse($result->requiredSatisfied);
        $this->assertSame('blocked', $result->sourceAttempts[0]->outcome);
    }

    public function testHandlesHttpErrors(): void
    {
        $candidate = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $fetchResult = new FetchResult(
            content: 'Not Found',
            contentType: 'text/html',
            statusCode: 404,
            url: 'https://example.com/quote',
            finalUrl: 'https://example.com/quote',
            retrievedAt: new DateTimeImmutable(),
        );

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $result = $this->handler->collect($request);

        $this->assertEmpty($result->found);
        $this->assertFalse($result->requiredSatisfied);
        $this->assertSame('http_error', $result->sourceAttempts[0]->outcome);
        $this->assertSame(404, $result->sourceAttempts[0]->httpStatus);
    }

    public function testHandlesHistoricalExtractions(): void
    {
        $candidate = $this->createCandidate('https://api.example.com/financials', 'fmp', 1);
        $request = new CollectBatchRequest(
            datapointKeys: ['financials.revenue'],
            requiredKeys: ['financials.revenue'],
            sourceCandidates: [$candidate],
            ticker: 'AAPL',
        );

        $fetchResult = $this->createFetchResult('https://api.example.com/financials');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        $this->webFetchClient
            ->method('fetch')
            ->willReturn($fetchResult);

        $historicalExtraction = new HistoricalExtraction(
            datapointKey: 'financials.revenue',
            periods: [
                new PeriodValue(new DateTimeImmutable('2024-09-30'), 400_000_000_000.0),
                new PeriodValue(new DateTimeImmutable('2023-09-30'), 380_000_000_000.0),
            ],
            unit: 'currency',
            locator: new SourceLocator(SourceLocatorType::Json, '$.revenue', '400000000000'),
            currency: 'USD',
            scale: 'units',
        );

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'fmp',
                extractions: [],
                notFound: [],
                historicalExtractions: ['financials.revenue' => $historicalExtraction],
            ));

        $result = $this->handler->collect($request);

        $this->assertArrayHasKey('financials.revenue', $result->historicalFound);
        $this->assertTrue($result->requiredSatisfied);
    }

    public function testGroupsByUrlToAvoidDuplicateFetches(): void
    {
        // Same URL, different adapters - should only fetch once
        $candidate1 = $this->createCandidate('https://example.com/quote', 'yahoo', 1);
        $candidate2 = $this->createCandidate('https://example.com/quote', 'yahoo_alt', 2);

        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate1, $candidate2],
            ticker: 'AAPL',
        );

        $fetchResult = $this->createFetchResult('https://example.com/quote');

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        // Expect fetch to be called only once due to URL deduplication
        $this->webFetchClient
            ->expects($this->once())
            ->method('fetch')
            ->willReturn($fetchResult);

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'yahoo',
                extractions: [
                    'valuation.market_cap' => $this->createExtraction('valuation.market_cap', 3_000_000_000_000.0, 'currency'),
                ],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        $this->assertCount(1, $result->found);
    }

    public function testRecordsAllSourceAttempts(): void
    {
        $candidate1 = $this->createCandidate('https://source1.com/quote', 'source1', 1);
        $candidate2 = $this->createCandidate('https://source2.com/quote', 'source2', 2);

        $request = new CollectBatchRequest(
            datapointKeys: ['valuation.market_cap'],
            requiredKeys: ['valuation.market_cap'],
            sourceCandidates: [$candidate1, $candidate2],
            ticker: 'AAPL',
        );

        $this->webFetchClient
            ->method('isRateLimited')
            ->willReturn(false);

        // First source fails, second succeeds
        $this->webFetchClient
            ->method('fetch')
            ->willReturnCallback(function (FetchRequest $req) {
                if (str_contains($req->url, 'source1')) {
                    return new FetchResult(
                        content: 'Error',
                        contentType: 'text/html',
                        statusCode: 500,
                        url: $req->url,
                        finalUrl: $req->url,
                        retrievedAt: new DateTimeImmutable(),
                    );
                }
                return $this->createFetchResult($req->url);
            });

        $this->sourceAdapter
            ->method('adapt')
            ->willReturn(new AdaptResult(
                adapterId: 'source2',
                extractions: [
                    'valuation.market_cap' => $this->createExtraction('valuation.market_cap', 3_000_000_000_000.0, 'currency'),
                ],
                notFound: [],
            ));

        $result = $this->handler->collect($request);

        // Should have 2 attempts recorded
        $this->assertCount(2, $result->sourceAttempts);
    }

    private function createCandidate(string $url, string $adapterId, int $priority): SourceCandidate
    {
        return new SourceCandidate(
            url: $url,
            adapterId: $adapterId,
            domain: parse_url($url, PHP_URL_HOST) ?? 'unknown',
            priority: $priority,
        );
    }

    private function createFetchResult(string $url): FetchResult
    {
        return new FetchResult(
            content: '<html>Test content</html>',
            contentType: 'text/html',
            statusCode: 200,
            url: $url,
            finalUrl: $url,
            retrievedAt: new DateTimeImmutable(),
        );
    }

    private function createExtraction(string $key, float $value, string $unit): Extraction
    {
        return new Extraction(
            datapointKey: $key,
            rawValue: $value,
            unit: $unit,
            currency: $unit === 'currency' ? 'USD' : null,
            scale: 'units',
            asOf: new DateTimeImmutable(),
            locator: new SourceLocator(SourceLocatorType::Html, 'td.value', (string) $value),
        );
    }
}
