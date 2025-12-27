<?php

declare(strict_types=1);

namespace tests\unit\enums;

use app\enums\CollectionMethod;
use Codeception\Test\Unit;

/**
 * @covers \app\enums\CollectionMethod
 */
final class CollectionMethodTest extends Unit
{
    public function testAllCasesHaveStringValues(): void
    {
        $this->assertSame('web_fetch', CollectionMethod::WebFetch->value);
        $this->assertSame('web_search', CollectionMethod::WebSearch->value);
        $this->assertSame('api', CollectionMethod::Api->value);
        $this->assertSame('cache', CollectionMethod::Cache->value);
        $this->assertSame('derived', CollectionMethod::Derived->value);
        $this->assertSame('not_found', CollectionMethod::NotFound->value);
    }

    public function testFromStringCreatesEnum(): void
    {
        $this->assertSame(CollectionMethod::WebFetch, CollectionMethod::from('web_fetch'));
        $this->assertSame(CollectionMethod::NotFound, CollectionMethod::from('not_found'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(CollectionMethod::tryFrom('invalid'));
    }

    public function testCasesReturnsAllMethods(): void
    {
        $cases = CollectionMethod::cases();

        $this->assertCount(6, $cases);
    }
}
