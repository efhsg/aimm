<?php

declare(strict_types=1);

namespace tests\unit\adapters;

use app\adapters\EcbAdapter;
use app\dto\AdaptRequest;
use app\dto\FetchResult;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\adapters\EcbAdapter
 */
final class EcbAdapterTest extends Unit
{
    private const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';

    public function testParsesHistoricalFxRates(): void
    {
        $content = $this->loadFixture('ecb/eurofxref-hist.xml');
        $adapter = new EcbAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/xml',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['macro.fx_rates'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame('ecb', $result->adapterId);
        $this->assertSame([], $result->notFound);
        $this->assertNull($result->parseError);
        $this->assertCount(1, $result->historicalExtractions);

        $fxRates = $result->historicalExtractions['macro.fx_rates'];
        $this->assertSame('macro.fx_rates', $fxRates->datapointKey);
        $this->assertSame('ratio', $fxRates->unit);
        $this->assertSame('EUR/USD', $fxRates->currency);

        // Should have 5 periods from fixture
        $this->assertCount(5, $fxRates->periods);

        // Newest period first (2024-01-15)
        $this->assertSame('2024-01-15', $fxRates->periods[0]->endDate->format('Y-m-d'));
        $this->assertSame(1.0892, $fxRates->periods[0]->value);

        // Second period (2024-01-12)
        $this->assertSame('2024-01-12', $fxRates->periods[1]->endDate->format('Y-m-d'));
        $this->assertSame(1.0958, $fxRates->periods[1]->value);

        // Oldest period last (2023-06-15)
        $this->assertSame('2023-06-15', $fxRates->periods[4]->endDate->format('Y-m-d'));
        $this->assertSame(1.0938, $fxRates->periods[4]->value);
    }

    public function testReturnsNotFoundForUnsupportedKeys(): void
    {
        $content = $this->loadFixture('ecb/eurofxref-hist.xml');
        $adapter = new EcbAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'application/xml',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['unsupported.key'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['unsupported.key'], $result->notFound);
        $this->assertEmpty($result->extractions);
        $this->assertEmpty($result->historicalExtractions);
    }

    public function testReturnsParseErrorForNonXmlContent(): void
    {
        $adapter = new EcbAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: '{"error": "not xml"}',
                contentType: 'application/json',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['macro.fx_rates'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['macro.fx_rates'], $result->notFound);
        $this->assertStringContainsString('requires XML content', $result->parseError ?? '');
    }

    public function testReturnsParseErrorForInvalidXml(): void
    {
        $adapter = new EcbAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: 'not valid xml <<<<',
                contentType: 'text/xml',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['macro.fx_rates'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['macro.fx_rates'], $result->notFound);
        $this->assertSame('Failed to parse ECB FX rates XML', $result->parseError);
    }

    public function testReturnsParseErrorForEmptyRates(): void
    {
        $adapter = new EcbAdapter();
        $emptyXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube></Cube>
</gesmes:Envelope>
XML;

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $emptyXml,
                contentType: 'text/xml',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['macro.fx_rates'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame(['macro.fx_rates'], $result->notFound);
        $this->assertSame('Failed to parse ECB FX rates XML', $result->parseError);
    }

    public function testGetAdapterIdReturnsEcb(): void
    {
        $adapter = new EcbAdapter();
        $this->assertSame('ecb', $adapter->getAdapterId());
    }

    public function testGetSupportedKeysReturnsFxRates(): void
    {
        $adapter = new EcbAdapter();
        $keys = $adapter->getSupportedKeys();

        $this->assertSame(['macro.fx_rates'], $keys);
    }

    public function testHandlesTextXmlContentType(): void
    {
        $content = $this->loadFixture('ecb/eurofxref-hist.xml');
        $adapter = new EcbAdapter();

        $request = new AdaptRequest(
            fetchResult: new FetchResult(
                content: $content,
                contentType: 'text/xml; charset=UTF-8',
                statusCode: 200,
                url: self::ECB_URL,
                finalUrl: self::ECB_URL,
                retrievedAt: new DateTimeImmutable('2024-01-15T12:00:00Z'),
            ),
            datapointKeys: ['macro.fx_rates'],
        );

        $result = $adapter->adapt($request);

        $this->assertSame([], $result->notFound);
        $this->assertCount(1, $result->historicalExtractions);
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
