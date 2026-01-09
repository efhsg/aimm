<?php

declare(strict_types=1);

namespace tests\unit\factories\pdf;

use app\dto\pdf\RenderBundle;
use app\exceptions\BundleSizeExceededException;
use app\exceptions\RenderBundleValidationException;
use app\exceptions\SecurityException;
use app\factories\pdf\RenderBundleFactory;
use Codeception\Test\Unit;

/**
 * @covers \app\factories\pdf\RenderBundleFactory
 * @covers \app\dto\pdf\RenderBundle
 */
final class RenderBundleFactoryTest extends Unit
{
    public function testBuildThrowsWhenMissingIndexHtml(): void
    {
        $factory = new RenderBundleFactory('trace');

        $this->expectException(RenderBundleValidationException::class);

        $factory->build();
    }

    public function testRejectsExternalRefsInIndexHtml(): void
    {
        $factory = new RenderBundleFactory('trace');

        $this->expectException(SecurityException::class);

        $factory->withIndexHtml('<img src="https://example.com/logo.png">');
    }

    public function testRejectsExternalRefsInCss(): void
    {
        $factory = new RenderBundleFactory('trace');
        $factory->withIndexHtml('<html></html>');

        $this->expectException(SecurityException::class);

        $factory->addFile('assets/test.css', 'body { background: url(https://example.com/bg.png); }');
    }

    public function testRejectsPathTraversal(): void
    {
        $factory = new RenderBundleFactory('trace');
        $factory->withIndexHtml('<html></html>');

        $this->expectException(SecurityException::class);

        $factory->addFile('../secrets.txt', 'secret');
    }

    public function testThrowsWhenBundleExceedsLimit(): void
    {
        $factory = new RenderBundleFactory('trace');
        $factory->withIndexHtml('<html></html>');

        $this->expectException(BundleSizeExceededException::class);

        $factory->addFile('assets/big.bin', 'x', 50 * 1024 * 1024 + 1);
        $factory->build();
    }

    public function testBuildReturnsRenderBundle(): void
    {
        $factory = new RenderBundleFactory('trace');

        $bundle = $factory
            ->withIndexHtml('<html></html>')
            ->withHeaderHtml('<header>Header</header>')
            ->withFooterHtml('<footer>Footer</footer>')
            ->addFile('assets/test.css', 'body { color: #000; }', 24)
            ->build();

        $this->assertInstanceOf(RenderBundle::class, $bundle);
        $this->assertSame('trace', $bundle->traceId);
        $this->assertSame('<html></html>', $bundle->indexHtml);
        $this->assertSame('<header>Header</header>', $bundle->headerHtml);
        $this->assertSame('<footer>Footer</footer>', $bundle->footerHtml);
        $this->assertArrayHasKey('assets/test.css', $bundle->files);
    }
}
