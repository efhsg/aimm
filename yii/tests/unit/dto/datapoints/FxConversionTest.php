<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\FxConversion;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * @covers \app\dto\datapoints\FxConversion
 */
final class FxConversionTest extends Unit
{
    public function testConstructorSetsAllProperties(): void
    {
        $rateAsOf = new DateTimeImmutable('2024-01-15');

        $conversion = new FxConversion(
            originalCurrency: 'GBP',
            originalValue: 100.50,
            rate: 1.27,
            rateAsOf: $rateAsOf,
            rateSource: 'ECB',
        );

        $this->assertSame('GBP', $conversion->originalCurrency);
        $this->assertSame(100.50, $conversion->originalValue);
        $this->assertSame(1.27, $conversion->rate);
        $this->assertSame($rateAsOf, $conversion->rateAsOf);
        $this->assertSame('ECB', $conversion->rateSource);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $rateAsOf = new DateTimeImmutable('2024-01-15');

        $conversion = new FxConversion(
            originalCurrency: 'EUR',
            originalValue: 50.00,
            rate: 1.08,
            rateAsOf: $rateAsOf,
            rateSource: 'Reuters',
        );

        $array = $conversion->toArray();

        $this->assertSame([
            'original_currency' => 'EUR',
            'original_value' => 50.00,
            'rate' => 1.08,
            'rate_as_of' => '2024-01-15',
            'rate_source' => 'Reuters',
        ], $array);
    }

    public function testIsReadonly(): void
    {
        $conversion = new FxConversion(
            originalCurrency: 'GBP',
            originalValue: 100.00,
            rate: 1.25,
            rateAsOf: new DateTimeImmutable(),
            rateSource: 'ECB',
        );

        $reflection = new \ReflectionClass($conversion);
        $this->assertTrue($reflection->isReadOnly());
    }
}
