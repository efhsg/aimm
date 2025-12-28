<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\FxConversion;
use app\dto\datapoints\SourceLocator;
use app\enums\CollectionMethod;
use app\enums\DataScale;
use app\enums\SourceLocatorType;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\dto\datapoints\DataPointMoney
 */
final class DataPointMoneyTest extends Unit
{
    public function testUnitConstant(): void
    {
        $this->assertSame('currency', DataPointMoney::UNIT);
    }

    public function testConstructorWithRequiredParameters(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $locator = SourceLocator::html('div.price', '$150.5B');

        $datapoint = new DataPointMoney(
            value: 150.5,
            currency: 'USD',
            scale: DataScale::Billions,
            asOf: $asOf,
            sourceUrl: 'https://example.com/data',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $this->assertSame(150.5, $datapoint->value);
        $this->assertSame('USD', $datapoint->currency);
        $this->assertSame(DataScale::Billions, $datapoint->scale);
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
        $locator = SourceLocator::html('div.price', '$150.5B');
        $fxConversion = new FxConversion('GBP', 120.0, 1.25, $asOf, 'ECB');

        $datapoint = new DataPointMoney(
            value: 150.5,
            currency: 'USD',
            scale: DataScale::Billions,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::Derived,
            sourceLocator: $locator,
            attemptedSources: ['yahoo', 'reuters'],
            derivedFrom: ['revenue', 'costs'],
            formula: 'revenue - costs',
            fxConversion: $fxConversion,
            cacheSource: 'redis',
            cacheAgeDays: 2,
        );

        $this->assertSame($locator, $datapoint->sourceLocator);
        $this->assertSame(['yahoo', 'reuters'], $datapoint->attemptedSources);
        $this->assertSame(['revenue', 'costs'], $datapoint->derivedFrom);
        $this->assertSame('revenue - costs', $datapoint->formula);
        $this->assertSame($fxConversion, $datapoint->fxConversion);
        $this->assertSame('redis', $datapoint->cacheSource);
        $this->assertSame(2, $datapoint->cacheAgeDays);
    }

    public function testGetBaseValueWithUnits(): void
    {
        $datapoint = $this->createDataPoint(100.0, DataScale::Units);
        $this->assertSame(100.0, $datapoint->getBaseValue());
    }

    public function testGetBaseValueWithThousands(): void
    {
        $datapoint = $this->createDataPoint(100.0, DataScale::Thousands);
        $this->assertSame(100_000.0, $datapoint->getBaseValue());
    }

    public function testGetBaseValueWithMillions(): void
    {
        $datapoint = $this->createDataPoint(100.0, DataScale::Millions);
        $this->assertSame(100_000_000.0, $datapoint->getBaseValue());
    }

    public function testGetBaseValueWithBillions(): void
    {
        $datapoint = $this->createDataPoint(100.0, DataScale::Billions);
        $this->assertSame(100_000_000_000.0, $datapoint->getBaseValue());
    }

    public function testGetBaseValueWithTrillions(): void
    {
        $datapoint = $this->createDataPoint(1.5, DataScale::Trillions);
        $this->assertSame(1_500_000_000_000.0, $datapoint->getBaseValue());
    }

    public function testGetBaseValueWithNullValue(): void
    {
        $datapoint = $this->createDataPoint(null, DataScale::Billions);
        $this->assertNull($datapoint->getBaseValue());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $locator = SourceLocator::html('div.price', '$150.5B');

        $datapoint = new DataPointMoney(
            value: 150.5,
            currency: 'USD',
            scale: DataScale::Billions,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertSame(150.5, $array['value']);
        $this->assertSame(DataPointMoney::UNIT, $array['unit']);
        $this->assertSame('USD', $array['currency']);
        $this->assertSame(DataScale::Billions->value, $array['scale']);
        $this->assertSame('2024-01-15', $array['as_of']);
        $this->assertSame('https://example.com', $array['source_url']);
        $this->assertSame(CollectionMethod::WebFetch->value, $array['method']);
        $this->assertIsArray($array['source_locator']);
        $this->assertNull($array['fx_conversion']);
    }

    public function testToArrayIncludesNestedObjects(): void
    {
        $asOf = new DateTimeImmutable('2024-01-15');
        $retrievedAt = new DateTimeImmutable();
        $locator = SourceLocator::html('div.price', '$150.5B');

        $datapoint = new DataPointMoney(
            value: 150.5,
            currency: 'USD',
            scale: DataScale::Billions,
            asOf: $asOf,
            sourceUrl: 'https://example.com',
            retrievedAt: $retrievedAt,
            method: CollectionMethod::WebFetch,
            sourceLocator: $locator,
        );

        $array = $datapoint->toArray();

        $this->assertIsArray($array['source_locator']);
        $this->assertSame(SourceLocatorType::Html->value, $array['source_locator']['type']);
    }

    public function testIsReadonly(): void
    {
        $datapoint = $this->createDataPoint(100.0, DataScale::Units);

        $reflection = new \ReflectionClass($datapoint);
        $this->assertTrue($reflection->isReadOnly());
    }

    public function testWebFetchRequiresSourceUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Method web_fetch requires sourceUrl');

        new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
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

        new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
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

        new DataPointMoney(
            value: null,
            currency: 'USD',
            scale: DataScale::Units,
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

        new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
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

        new DataPointMoney(
            value: 100.0,
            currency: 'USD',
            scale: DataScale::Units,
            asOf: new DateTimeImmutable(),
            sourceUrl: null,
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::Cache,
        );
    }

    private function createDataPoint(?float $value, DataScale $scale): DataPointMoney
    {
        return new DataPointMoney(
            value: $value,
            currency: 'USD',
            scale: $scale,
            asOf: new DateTimeImmutable(),
            sourceUrl: 'https://example.com',
            retrievedAt: new DateTimeImmutable(),
            method: CollectionMethod::WebFetch,
            sourceLocator: SourceLocator::html('div.price', '$100'),
        );
    }
}
