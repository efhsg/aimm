<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\SourceLocator;
use app\enums\CollectionMethod;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\dto\datapoints\DataPointNumber
 */
final class DataPointNumberTest extends Unit
{
    public function testUnitConstant(): void
    {
        $this->assertSame('number', DataPointNumber::UNIT);
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $locator = SourceLocator::json('$.index.value', '4532.75');

        $datapoint = new DataPointNumber(
            value: 4532.75,
            asOf: $asOf,
            sourceUrl: 'https://example.com/data',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $this->assertSame(4532.75, $datapoint->value);
        $this->assertSame($asOf, $datapoint->asOf);
        $this->assertSame('https://example.com/data', $datapoint->sourceUrl);
        $this->assertSame($retrievedAt, $datapoint->retrievedAt);
        $this->assertSame(CollectionMethod::WebFetch, $datapoint->method);
        $this->assertSame($locator, $datapoint->sourceLocator);
    }

    public function testConstructorWithAllOptionalParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable();
        $locator = SourceLocator::json('$.index.value', '4532.75');

        $datapoint = new DataPointNumber(
            value: 4532.75,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::Api,
            sourceLocator: $locator,
            attemptedSources: ['yahoo', 'bloomberg'],
            derivedFrom: ['component1', 'component2'],
            formula: 'weighted_average(components)',
            cacheSource: 'database',
            cacheAgeDays: 0,
        );

        $this->assertSame($locator, $datapoint->sourceLocator);
        $this->assertSame(['yahoo', 'bloomberg'], $datapoint->attemptedSources);
        $this->assertSame(['component1', 'component2'], $datapoint->derivedFrom);
        $this->assertSame('weighted_average(components)', $datapoint->formula);
        $this->assertSame('database', $datapoint->cacheSource);
        $this->assertSame(0, $datapoint->cacheAgeDays);
    }

    public function testNullValueIsAllowed(): void
    {
        $datapoint = new DataPointNumber(
            value: null,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::NotFound,
            attemptedSources: ['yahoo'],
        );

        $this->assertNull($datapoint->value);
        $this->assertSame(CollectionMethod::NotFound, $datapoint->method);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $locator = SourceLocator::json('$.index.value', '4532.75');

        $datapoint = new DataPointNumber(
            value: 4532.75,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertSame(4532.75, $array['value']);
        $this->assertSame('number', $array['unit']);
        $this->assertSame('2024-01-15', $array['as_of']);
        $this->assertSame('https://example.com', $array['source_url']);
        $this->assertSame('web_fetch', $array['method']);
        $this->assertIsArray($array['source_locator']);
        $this->assertNull($array['attempted_sources']);
    }

    public function testToArrayIncludesSourceLocator(): void
    {
        $locator = SourceLocator::html('span.index', '4532.75');

        $datapoint = new DataPointNumber(
            value: 4532.75,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertIsArray($array['source_locator']);
        $this->assertSame('html', $array['source_locator']['type']);
        $this->assertSame('span.index', $array['source_locator']['selector']);
    }

    public function testIsReadonly(): void
    {
        $datapoint = new DataPointNumber(
            value: 100.0,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('span.value', '100'),
        );

        $reflection = new \ReflectionClass($datapoint);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testWebFetchRequiresSourceUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method web_fetch requires sourceUrl');

        new DataPointNumber(
            value: 100.0,
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

        new DataPointNumber(
            value: 100.0,
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

        new DataPointNumber(
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

        new DataPointNumber(
            value: 100.0,
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

        new DataPointNumber(
            value: 100.0,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Cache,
        );
    }
}
