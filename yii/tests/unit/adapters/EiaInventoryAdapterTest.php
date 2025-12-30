<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\EiaInventoryAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\EiaInventoryAdapter
 */
final class EiaInventoryAdapterTest extends Unit
{
    public function testParsesInventoryFromJson(): void
    {
        $content = $this->loadFixture('eia/inventory.json');

        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['inventory'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertArrayHasKey('inventory', $result->extractions);

        $extraction = $result->extractions['inventory'];
        $this->assertSame(837793.0, $extraction->rawValue);
        $this->assertSame('MBBL', $extraction->unit);
        $this->assertSame('2025-12-19', $extraction->asOf?->format('Y-m-d'));
    }

    public function testParsesOilInventoryFromJson(): void
    {
        $content = $this->loadFixture('eia/inventory.json');

        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['oil_inventory'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertArrayHasKey('oil_inventory', $result->extractions);

        $extraction = $result->extractions['oil_inventory'];
        $this->assertSame(837793.0, $extraction->rawValue);
        $this->assertSame('MBBL', $extraction->unit);
        $this->assertSame('2025-12-19', $extraction->asOf?->format('Y-m-d'));
    }

    public function testReturnsNotFoundForUnsupportedKey(): void
    {
        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '{"response":{"data":[{"value":100}]}}',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['unsupported_key'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['unsupported_key'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Unsupported datapoint key', $result->parseError);
    }

    public function testReturnsNotFoundForNonJsonContent(): void
    {
        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '<html>Not JSON</html>',
                contentType: 'text/html',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['inventory'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['inventory'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Unsupported content type', $result->parseError);
    }

    public function testReturnsNotFoundForMissingResponseData(): void
    {
        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '{"response":{}}',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['inventory'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['inventory'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Missing response data', $result->parseError);
    }

    public function testReturnsNotFoundForNonNumericValue(): void
    {
        $adapter = new EiaInventoryAdapter();
        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '{"response":{"data":[{"value":"not a number"}]}}',
                contentType: 'application/json',
                statusCode: 200,
                url: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                finalUrl: 'https://api.eia.gov/v2/seriesid/PET.WCRSTUS1.W?api_key=DEMO_KEY',
                retrievedAt: new DateTimeImmutable('2025-12-23T00:00:00Z'),
            ),
            datapointKeys: ['inventory'],
            ticker: null,
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['inventory'], $result->notFound);
        $this->assertSame([], $result->extractions);
        $this->assertSame('Inventory value missing', $result->parseError);
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
