<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\DataPointRatio;
use app\dto\datapoints\SourceLocator;
use app\enums\CollectionMethod;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\dto\datapoints\DataPointRatio
 */
final class DataPointRatioTest extends Unit
{
    public function testUnitConstant(): void
    {
        $this->assertSame('ratio', DataPointRatio::UNIT);
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $locator = SourceLocator::json('$.pe_ratio', '15.5');

        $datapoint = new DataPointRatio(
            value: 15.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com/data',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $this->assertSame(15.5, $datapoint->value);
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
        $locator = SourceLocator::json('$.pe_ratio', '15.5');

        $datapoint = new DataPointRatio(
            value: 15.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::Derived,
            sourceLocator: $locator,
            attemptedSources: ['yahoo', 'reuters'],
            derivedFrom: ['price', 'eps'],
            formula: 'price / eps',
            cacheSource: 'redis',
            cacheAgeDays: 1,
        );

        $this->assertSame($locator, $datapoint->sourceLocator);
        $this->assertSame(['yahoo', 'reuters'], $datapoint->attemptedSources);
        $this->assertSame(['price', 'eps'], $datapoint->derivedFrom);
        $this->assertSame('price / eps', $datapoint->formula);
        $this->assertSame('redis', $datapoint->cacheSource);
        $this->assertSame(1, $datapoint->cacheAgeDays);
    }

    public function testNullValueIsAllowed(): void
    {
        $datapoint = new DataPointRatio(
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
        $locator = SourceLocator::json('$.pe_ratio', '12.5');

        $datapoint = new DataPointRatio(
            value: 12.5,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::Api,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertSame(12.5, $array['value']);
        $this->assertSame('ratio', $array['unit']);
        $this->assertSame('2024-01-15', $array['as_of']);
        $this->assertSame('https://example.com', $array['source_url']);
        $this->assertSame('api', $array['method']);
        $this->assertIsArray($array['source_locator']);
        $this->assertNull($array['attempted_sources']);
    }

    public function testIsReadonly(): void
    {
        $datapoint = new DataPointRatio(
            value: 10.0,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('span.ratio', '10.0'),
        );

        $reflection = new \ReflectionClass($datapoint);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testWebFetchRequiresSourceUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method web_fetch requires sourceUrl');

        new DataPointRatio(
            value: 10.0,
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

        new DataPointRatio(
            value: 10.0,
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

        new DataPointRatio(
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

        new DataPointRatio(
            value: 10.0,
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

        new DataPointRatio(
            value: 10.0,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Cache,
        );
    }
}
