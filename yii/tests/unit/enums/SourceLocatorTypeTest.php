<?php

declare(strict_types=1);

namespace tests\unit\enums;

use app\enums\SourceLocatorType;
use Codeception\Test\Unit;

/**
 * @covers \app\enums\SourceLocatorType
 */
final class SourceLocatorTypeTest extends Unit
{
    public function testValuesMatchExpectedStrings(): void
    {
        $this->assertSame('html', SourceLocatorType::Html->value);
        $this->assertSame('json', SourceLocatorType::Json->value);
        $this->assertSame('xpath', SourceLocatorType::Xpath->value);
    }
}
