<?php

declare(strict_types=1);

namespace tests\unit\enums;

use app\enums\Severity;
use Codeception\Test\Unit;

/**
 * @covers \app\enums\Severity
 */
final class SeverityTest extends Unit
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('required', Severity::Required->value);
        $this->assertSame('recommended', Severity::Recommended->value);
        $this->assertSame('optional', Severity::Optional->value);
    }

    public function testFromStringCreatesEnum(): void
    {
        $this->assertSame(Severity::Required, Severity::from('required'));
        $this->assertSame(Severity::Optional, Severity::from('optional'));
    }

    public function testCasesReturnsAllSeverities(): void
    {
        $cases = Severity::cases();

        $this->assertCount(3, $cases);
    }
}
