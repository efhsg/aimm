<?php

declare(strict_types=1);

namespace tests\unit\enums;

use app\enums\DataScale;
use Codeception\Test\Unit;

/**
 * @covers \app\enums\DataScale
 */
final class DataScaleTest extends Unit
{
    public function testValuesMatchExpectedStrings(): void
    {
        $this->assertSame('units', DataScale::Units->value);
        $this->assertSame('thousands', DataScale::Thousands->value);
        $this->assertSame('millions', DataScale::Millions->value);
        $this->assertSame('billions', DataScale::Billions->value);
        $this->assertSame('trillions', DataScale::Trillions->value);
    }
}
