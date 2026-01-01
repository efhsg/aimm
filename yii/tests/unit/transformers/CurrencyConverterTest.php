<?php

declare(strict_types=1);

namespace tests\unit\transformers;

use app\queries\FxRateQuery;
use app\transformers\CurrencyConverter;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class CurrencyConverterTest extends Unit
{
    public function testConvertSameCurrencyReturnsAmount()
    {
        $query = $this->createMock(FxRateQuery::class);
        $converter = new CurrencyConverter($query);

        $this->assertEquals(100.0, $converter->convert(100.0, 'USD', 'USD', new DateTimeImmutable()));
    }

    public function testConvertFromEurToUsd()
    {
        $query = $this->createMock(FxRateQuery::class);
        $query->method('findClosestRate')->willReturn(1.10);

        $converter = new CurrencyConverter($query);
        // 100 EUR * 1.10 = 110.00 USD
        $this->assertEquals(110.0, $converter->convert(100.0, 'EUR', 'USD', new DateTimeImmutable()));
    }

    public function testConvertCrossRateUsdToGbp()
    {
        $query = $this->createMock(FxRateQuery::class);
        $query->method('findClosestRate')->willReturnCallback(function ($currency, $date) {
            if ($currency === 'USD') {
                return 1.10;
            }
            if ($currency === 'GBP') {
                return 0.85;
            }
            return null;
        });

        $converter = new CurrencyConverter($query);
        // 110 USD -> 100 EUR -> 85 GBP
        // Rate = 0.85 / 1.10 = 0.7727...
        // 110 * 0.7727 = 85.0

        $date = new DateTimeImmutable('2025-01-01');
        $this->assertEquals(85.0, $converter->convert(110.0, 'USD', 'GBP', $date));
    }
}
