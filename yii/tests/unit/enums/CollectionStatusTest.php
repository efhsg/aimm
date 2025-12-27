<?php

declare(strict_types=1);

namespace tests\unit\enums;

use app\enums\CollectionStatus;
use Codeception\Test\Unit;

/**
 * @covers \app\enums\CollectionStatus
 */
final class CollectionStatusTest extends Unit
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('complete', CollectionStatus::Complete->value);
        $this->assertSame('partial', CollectionStatus::Partial->value);
        $this->assertSame('failed', CollectionStatus::Failed->value);
    }

    public function testFromStringCreatesEnum(): void
    {
        $this->assertSame(CollectionStatus::Complete, CollectionStatus::from('complete'));
        $this->assertSame(CollectionStatus::Failed, CollectionStatus::from('failed'));
    }

    public function testCasesReturnsAllStatuses(): void
    {
        $cases = CollectionStatus::cases();

        $this->assertCount(3, $cases);
    }
}
