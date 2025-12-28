<?php

declare(strict_types=1);

namespace tests\unit\dto\datapoints;

use app\dto\datapoints\SourceLocator;
use Codeception\Test\Unit;

/**
 * @covers \app\dto\datapoints\SourceLocator
 */
final class SourceLocatorTest extends Unit
{
    public function testHtmlFactoryCreatesCorrectType(): void
    {
        $locator = SourceLocator::html('div.price', '$123.45');

        $this->assertSame('html', $locator->type);
        $this->assertSame('div.price', $locator->selector);
        $this->assertSame('$123.45', $locator->snippet);
    }

    public function testJsonFactoryCreatesCorrectType(): void
    {
        $locator = SourceLocator::json('$.data.price', '123.45');

        $this->assertSame('json', $locator->type);
        $this->assertSame('$.data.price', $locator->selector);
        $this->assertSame('123.45', $locator->snippet);
    }

    public function testXpathFactoryCreatesCorrectType(): void
    {
        $locator = SourceLocator::xpath('//div[@class="price"]', '$123.45');

        $this->assertSame('xpath', $locator->type);
        $this->assertSame('//div[@class="price"]', $locator->selector);
        $this->assertSame('$123.45', $locator->snippet);
    }

    public function testSnippetTruncatedToMaxLength(): void
    {
        $longSnippet = str_repeat('a', 150);
        $locator = SourceLocator::html('div', $longSnippet);

        $this->assertSame(100, mb_strlen($locator->snippet));
        $this->assertStringEndsWith('...', $locator->snippet);
    }

    public function testSnippetNotTruncatedWhenExactlyMaxLength(): void
    {
        $snippet = str_repeat('a', 100);
        $locator = SourceLocator::html('div', $snippet);

        $this->assertSame(100, mb_strlen($locator->snippet));
        $this->assertStringEndsNotWith('...', $locator->snippet);
    }

    public function testSnippetNotTruncatedWhenUnderMaxLength(): void
    {
        $snippet = 'short snippet';
        $locator = SourceLocator::html('div', $snippet);

        $this->assertSame($snippet, $locator->snippet);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $locator = SourceLocator::html('div.price', '$123.45');
        $array = $locator->toArray();

        $this->assertSame([
            'type' => 'html',
            'selector' => 'div.price',
            'snippet' => '$123.45',
        ], $array);
    }

    public function testIsReadonly(): void
    {
        $locator = SourceLocator::html('div', 'test');

        $reflection = new \ReflectionClass($locator);
        $this->assertTrue($reflection->isReadOnly());
    }
}
