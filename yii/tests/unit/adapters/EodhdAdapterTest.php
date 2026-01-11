<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\EodhdAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\EodhdAdapter
 */
final class EodhdAdapterTest extends Unit
{
    public function testGetAdapterIdReturnsEodhd(): void
    {
        $adapter = new EodhdAdapter();

        $this->assertSame('eodhd', $adapter->getAdapterId());
    }

    public function testGetSupportedKeysReturnsAllMappedKeys(): void
    {
        $adapter = new EodhdAdapter();
        $keys = $adapter->getSupportedKeys();

        // Dividend keys
        $this->assertContains('dividends.history', $keys);
        $this->assertContains('dividends.annual_total', $keys);
        $this->assertContains('dividends.latest', $keys);
        $this->assertContains('dividends.ex_date', $keys);
        $this->assertContains('dividends.payment_date', $keys);
        $this->assertContains('dividends.record_date', $keys);

        // Split keys
        $this->assertContains('splits.history', $keys);
        $this->assertContains('splits.latest', $keys);
        $this->assertContains('splits.latest_date', $keys);
    }

    public function testParsesDividendsEndpoint(): void
    {
        $content = $this->loadFixture('eodhd/dividends.json');
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: [
                'dividends.history',
                'dividends.latest',
                'dividends.ex_date',
                'dividends.payment_date',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame('eodhd', $result->adapterId);
        $this->assertSame([], $result->notFound);

        // Historical extractions
        $this->assertCount(1, $result->historicalExtractions);
        $history = $result->historicalExtractions['dividends.history'];
        $this->assertCount(4, $history->periods);
        $this->assertSame('currency', $history->unit);
        $this->assertSame('USD', $history->currency);

        // Newest first (2024-11-08)
        $this->assertSame(0.99, $history->periods[0]->value);
        $this->assertSame('2024-11-08', $history->periods[0]->endDate->format('Y-m-d'));

        // Scalar extractions
        $this->assertCount(3, $result->extractions);

        $latest = $result->extractions['dividends.latest'];
        $this->assertSame(0.99, $latest->rawValue);
        $this->assertSame('currency', $latest->unit);
        $this->assertSame('USD', $latest->currency);

        $exDate = $result->extractions['dividends.ex_date'];
        $this->assertSame('2024-11-08', $exDate->rawValue);
        $this->assertSame('date', $exDate->unit);

        $paymentDate = $result->extractions['dividends.payment_date'];
        $this->assertSame('2024-12-10', $paymentDate->rawValue);
    }

    public function testCalculatesAnnualDividendTotal(): void
    {
        $content = $this->loadFixture('eodhd/dividends.json');
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: ['dividends.annual_total'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertArrayHasKey('dividends.annual_total', $result->extractions);

        $annualTotal = $result->extractions['dividends.annual_total'];
        // Fixture has 4 dividends in 2024: 0.99 + 0.99 + 0.95 + 0.95 = 3.88
        $this->assertSame(3.88, $annualTotal->rawValue);
        $this->assertSame('currency', $annualTotal->unit);
        $this->assertSame('USD', $annualTotal->currency);
    }

    public function testParsesSplitsEndpoint(): void
    {
        $content = $this->loadFixture('eodhd/splits.json');
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://eodhd.com/api/splits/AAPL.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/splits/AAPL.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: [
                'splits.history',
                'splits.latest',
                'splits.latest_date',
            ],
            ticker: 'AAPL',
        );

        $result = $adapter->adapt($request);

        $this->assertSame('eodhd', $result->adapterId);
        $this->assertSame([], $result->notFound);

        // Historical extractions
        $this->assertCount(1, $result->historicalExtractions);
        $history = $result->historicalExtractions['splits.history'];
        $this->assertCount(3, $history->periods);
        $this->assertSame('ratio', $history->unit);

        // Newest first (2020-08-31, 4-for-1 split = 4.0)
        $this->assertSame(4.0, $history->periods[0]->value);
        $this->assertSame('2020-08-31', $history->periods[0]->endDate->format('Y-m-d'));

        // 7-for-1 split
        $this->assertSame(7.0, $history->periods[1]->value);
        $this->assertSame('2014-06-09', $history->periods[1]->endDate->format('Y-m-d'));

        // Scalar extractions
        $this->assertCount(2, $result->extractions);

        $latest = $result->extractions['splits.latest'];
        $this->assertSame(4.0, $latest->rawValue);
        $this->assertSame('ratio', $latest->unit);

        $latestDate = $result->extractions['splits.latest_date'];
        $this->assertSame('2020-08-31', $latestDate->rawValue);
    }

    public function testHandlesEmptySplitsResponse(): void
    {
        $content = $this->loadFixture('eodhd/splits-empty.json');
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://eodhd.com/api/splits/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/splits/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: [
                'splits.history',
                'splits.latest',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertSame('eodhd', $result->adapterId);

        // Historical key should return empty periods (valid - no splits occurred)
        $this->assertCount(1, $result->historicalExtractions);
        $history = $result->historicalExtractions['splits.history'];
        $this->assertCount(0, $history->periods);

        // Scalar key should be not found
        $this->assertContains('splits.latest', $result->notFound);
    }

    public function testReturnsParseErrorForNonJsonContent(): void
    {
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '<html>Error</html>',
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: ['dividends.history'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertStringContainsString('requires JSON', $result->parseError);
        $this->assertContains('dividends.history', $result->notFound);
    }

    public function testReturnsNotFoundForUnsupportedKeys(): void
    {
        $content = $this->loadFixture('eodhd/dividends.json');
        $adapter = new EodhdAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: [
                'unsupported.key',
                'dividends.latest',
            ],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertContains('unsupported.key', $result->notFound);
        $this->assertArrayHasKey('dividends.latest', $result->extractions);
    }

    public function testDetectsApiErrorResponse(): void
    {
        $adapter = new EodhdAdapter();
        $errorResponse = json_encode(['error' => 'Invalid API token']);

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $errorResponse,
                contentType: 'application/json',
                statusCode: 401,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=invalid',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=invalid',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: ['dividends.history'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertStringContainsString('EODHD API error', $result->parseError);
        $this->assertContains('dividends.history', $result->notFound);
    }

    public function testDetectsRateLimitResponse(): void
    {
        $adapter = new EodhdAdapter();
        $rateLimitResponse = json_encode(['message' => 'API rate limit exceeded']);

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $rateLimitResponse,
                contentType: 'application/json',
                statusCode: 429,
                url: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                finalUrl: 'https://eodhd.com/api/div/XOM.US?fmt=json&api_token=demo',
                retrievedAt: new DateTimeImmutable('2024-12-01T00:00:00Z'),
            ),
            datapointKeys: ['dividends.history'],
            ticker: 'XOM',
        );

        $result = $adapter->adapt($request);

        $this->assertStringContainsString('rate limit', $result->parseError);
    }

    private function loadFixture(string $path): string
    {
        $fullPath = dirname(__DIR__, 2) . '/fixtures/' . $path;
        $content = file_get_contents($fullPath);
        if ($content === false) {
            $this->fail('Failed to load fixture: ' . $fullPath);
        }

        return $content;
    }
}
