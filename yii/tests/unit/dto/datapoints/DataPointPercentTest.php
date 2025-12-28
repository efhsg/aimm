<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\SourceLocator;
use app\enums\CollectionMethod;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\dto\datapoints\DataPointPercent
 */
final class DataPointPercentTest extends Unit
{
    public function testUnitConstant(): void
    {
        $this->assertSame('percent', DataPointPercent::UNIT);
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $locator = SourceLocator::html('span.yield', '4.5%');

        $datapoint = new DataPointPercent(
            value: 4.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com/data',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $this->assertSame(4.5, $datapoint->value);
        $this->assertSame($asOf, $datapoint->asOf);
        $this->assertSame('https://example.com/data', $datapoint->sourceUrl);
        $this->assertSame($retrievedAt, $datapoint->retrievedAt);
        $this->assertSame(CollectionMethod::WebFetch, $datapoint->method);
        $this->assertSame($locator, $datapoint->sourceLocator);
    }

    public function testGetDecimalValueConvertsPercentage(): void
    {
        $datapoint = $this->createDataPoint(4.5);

        $this->assertSame(0.045, $datapoint->getDecimalValue());
    }

    public function testGetDecimalValueWithZero(): void
    {
        $datapoint = $this->createDataPoint(0.0);

        $this->assertSame(0.0, $datapoint->getDecimalValue());
    }

    public function testGetDecimalValueWithNegative(): void
    {
        $datapoint = $this->createDataPoint(-2.5);

        $this->assertSame(-0.025, $datapoint->getDecimalValue());
    }

    public function testGetDecimalValueWithNullValue(): void
    {
        $datapoint = $this->createDataPoint(null);

        $this->assertNull($datapoint->getDecimalValue());
    }

    public function testGetDecimalValueWith100Percent(): void
    {
        $datapoint = $this->createDataPoint(100.0);

        $this->assertSame(1.0, $datapoint->getDecimalValue());
    }

    public function testConstructorWithAllOptionalParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable();
        $locator = SourceLocator::html('span.yield', '4.5%');

        $datapoint = new DataPointPercent(
            value: 4.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::Derived,
            sourceLocator: $locator,
            attemptedSources: ['yahoo'],
            derivedFrom: ['dividend', 'price'],
            formula: '(dividend / price) * 100',
            cacheSource: 'file',
            cacheAgeDays: 3,
        );

        $this->assertSame($locator, $datapoint->sourceLocator);
        $this->assertSame(['yahoo'], $datapoint->attemptedSources);
        $this->assertSame(['dividend', 'price'], $datapoint->derivedFrom);
        $this->assertSame('(dividend / price) * 100', $datapoint->formula);
        $this->assertSame('file', $datapoint->cacheSource);
        $this->assertSame(3, $datapoint->cacheAgeDays);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $locator = SourceLocator::html('span.yield', '4.5%');

        $datapoint = new DataPointPercent(
            value: 4.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertSame(4.5, $array['value']);
        $this->assertSame('percent', $array['unit']);
        $this->assertSame('2024-01-15', $array['as_of']);
        $this->assertSame('https://example.com', $array['source_url']);
        $this->assertSame('web_fetch', $array['method']);
        $this->assertIsArray($array['source_locator']);
    }

    public function testIsReadonly(): void
    {
        $datapoint = $this->createDataPoint(5.0);

        $reflection = new \ReflectionClass($datapoint);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testWebFetchRequiresSourceUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method web_fetch requires sourceUrl');

        new DataPointPercent(
            value: 4.5,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('div', 'test'),
        );
    }

    public function testWebFetchRequiresSourceLocator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method web_fetch requires sourceLocator');

        new DataPointPercent(
            value: 4.5,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
        );
    }

    public function testNotFoundRequiresAttemptedSources(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method not_found requires attemptedSources');

        new DataPointPercent(
            value: null,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::NotFound,
        );
    }

    public function testDerivedRequiresDerivedFromAndFormula(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method derived requires derivedFrom');

        new DataPointPercent(
            value: 4.5,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Derived,
        );
    }

    public function testCacheRequiresCacheSourceAndAgeDays(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method cache requires cacheSource');

        new DataPointPercent(
            value: 4.5,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Cache,
        );
    }

    private function createDataPoint(?float $value): DataPointPercent
    {
        return new DataPointPercent(
            value: $value,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('span.yield', '4.5%'),
        );
    }
}
