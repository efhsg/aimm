<?php

declare(strict_types=1);

namespace tests\unit\handlers\collection;

use app\dto\CollectPriceHistoryRequest;
use app\enums\CollectionStatus;
use app\handlers\collection\CollectPriceHistoryHandler;
use app\queries\PriceHistoryQuery;
use Codeception\Test\Unit;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use yii\log\Logger;

/**
 * @covers \app\handlers\collection\CollectPriceHistoryHandler
 */
final class CollectPriceHistoryHandlerTest extends Unit
{
    private PriceHistoryQuery $priceQuery;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priceQuery = $this->createMock(PriceHistoryQuery::class);
        $this->logger = $this->createMock(Logger::class);

        // Ensure API key is set for tests
        \Yii::$app->params['fmpApiKey'] = 'test_api_key';
    }

    public function testCollectsAndInsertsPriceHistory(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'XOM',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
            currency: 'USD',
        );

        $apiResponse = [
            'historical' => [
                ['date' => '2024-01-31', 'open' => 100.0, 'high' => 102.0, 'low' => 99.0, 'close' => 101.5, 'volume' => 1000000],
                ['date' => '2024-01-30', 'open' => 99.0, 'high' => 101.0, 'low' => 98.0, 'close' => 100.0, 'volume' => 900000],
                ['date' => '2024-01-29', 'open' => 98.0, 'high' => 100.0, 'low' => 97.0, 'close' => 99.0, 'volume' => 800000],
            ],
        ];

        $client = $this->createMockClient(200, $apiResponse);
        $this->priceQuery->method('findExistingDates')->willReturn([]);
        $this->priceQuery->method('bulkInsert')->willReturn(3);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame('XOM', $result->ticker);
        $this->assertSame(3, $result->recordsCollected);
        $this->assertSame(3, $result->recordsInserted);
        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertNull($result->error);
    }

    public function testSkipsExistingDates(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'CVX',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
        );

        $apiResponse = [
            'historical' => [
                ['date' => '2024-01-31', 'close' => 150.0, 'volume' => 500000],
                ['date' => '2024-01-30', 'close' => 149.0, 'volume' => 450000],
                ['date' => '2024-01-29', 'close' => 148.0, 'volume' => 400000],
            ],
        ];

        $client = $this->createMockClient(200, $apiResponse);
        // Two dates already exist
        $this->priceQuery->method('findExistingDates')->willReturn(['2024-01-31', '2024-01-30']);
        $this->priceQuery->method('bulkInsert')->willReturn(1);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(3, $result->recordsCollected);
        $this->assertSame(1, $result->recordsInserted);
        $this->assertSame(CollectionStatus::Complete, $result->status);
    }

    public function testHandlesRateLimitError(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'SHEL',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
        );

        $client = $this->createMockClient(200, ['Error Message' => 'Limit Reach. Please upgrade your plan.']);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(CollectionStatus::Failed, $result->status);
        $this->assertSame('FMP API rate limit exceeded', $result->error);
        $this->assertSame(0, $result->recordsCollected);
    }

    public function testHandlesHttpError(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'BP',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
        );

        $client = $this->createMockClient(500, ['error' => 'Internal Server Error']);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(CollectionStatus::Failed, $result->status);
        $this->assertStringContainsString('HTTP 500', $result->error);
    }

    public function testHandlesEmptyResponse(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'TTE',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
        );

        $client = $this->createMockClient(200, ['historical' => []]);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(CollectionStatus::Complete, $result->status);
        $this->assertSame(0, $result->recordsCollected);
        $this->assertSame(0, $result->recordsInserted);
    }

    public function testHandlesFlatArrayResponse(): void
    {
        // Arrange - some FMP endpoints return flat array without 'historical' wrapper
        $request = new CollectPriceHistoryRequest(
            ticker: 'XOM',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-05'),
        );

        $apiResponse = [
            ['date' => '2024-01-05', 'close' => 105.0, 'volume' => 1000000],
            ['date' => '2024-01-04', 'close' => 104.0, 'volume' => 900000],
        ];

        $client = $this->createMockClient(200, $apiResponse);
        $this->priceQuery->method('findExistingDates')->willReturn([]);
        $this->priceQuery->method('bulkInsert')->willReturn(2);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(2, $result->recordsCollected);
        $this->assertSame(CollectionStatus::Complete, $result->status);
    }

    public function testFailsWithoutApiKey(): void
    {
        // Arrange - temporarily remove API key
        $originalKey = \Yii::$app->params['fmpApiKey'] ?? null;
        \Yii::$app->params['fmpApiKey'] = null;

        $request = new CollectPriceHistoryRequest(
            ticker: 'XOM',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-31'),
        );

        $handler = $this->createHandler();

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertSame(CollectionStatus::Failed, $result->status);
        $this->assertSame('FMP API key not configured', $result->error);

        // Restore
        \Yii::$app->params['fmpApiKey'] = $originalKey;
    }

    public function testMasksApiKeyInAttemptUrl(): void
    {
        // Arrange
        $request = new CollectPriceHistoryRequest(
            ticker: 'XOM',
            from: new DateTimeImmutable('2024-01-01'),
            to: new DateTimeImmutable('2024-01-05'),
        );

        $client = $this->createMockClient(200, ['historical' => []]);

        $handler = $this->createHandler($client);

        // Act
        $result = $handler->collect($request);

        // Assert
        $this->assertNotEmpty($result->sourceAttempts);
        $attemptUrl = $result->sourceAttempts[0]->url;
        $this->assertStringContainsString('apikey=***', $attemptUrl);
        $this->assertStringNotContainsString('test_api_key', $attemptUrl);
    }

    private function createHandler(?Client $client = null): CollectPriceHistoryHandler
    {
        return new CollectPriceHistoryHandler(
            priceQuery: $this->priceQuery,
            logger: $this->logger,
            httpClient: $client,
        );
    }

    private function createMockClient(int $statusCode, mixed $data): Client
    {
        $body = is_array($data) ? json_encode($data) : (string) $data;
        $mock = new MockHandler([
            new Response($statusCode, ['Content-Type' => 'application/json'], $body),
        ]);
        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }
}
