<?php

declare(strict_types=1);

namespace tests\unit\dto\pdf;

use app\dto\pdf\RenderBundle;
use app\dto\pdf\RenderBundleBuilder;
use app\exceptions\BundleSizeExceededException;
use app\exceptions\RenderBundleValidationException;
use app\exceptions\SecurityException;
use Codeception\Test\Unit;

/**
 * @covers \app\dto\pdf\RenderBundleBuilder
 * @covers \app\dto\pdf\RenderBundle
 */
final class RenderBundleBuilderTest extends Unit
{
    public function testBuildThrowsWhenMissingIndexHtml(): void
    {
        $builder = new RenderBundleBuilder('trace');

        $this->expectException(RenderBundleValidationException::class);

        $builder->build();
    }

    public function testRejectsExternalRefsInIndexHtml(): void
    {
        $builder = new RenderBundleBuilder('trace');

        $this->expectException(SecurityException::class);

        $builder->withIndexHtml('<img src="https://example.com/logo.png">');
    }

    public function testRejectsExternalRefsInCss(): void
    {
        $builder = new RenderBundleBuilder('trace');
        $builder->withIndexHtml('<html></html>');

        $this->expectException(SecurityException::class);

        $builder->addFile('assets/test.css', 'body { background: url(https://example.com/bg.png); }');
    }

    public function testRejectsPathTraversal(): void
    {
        $builder = new RenderBundleBuilder('trace');
        $builder->withIndexHtml('<html></html>');

        $this->expectException(SecurityException::class);

        $builder->addFile('../secrets.txt', 'secret');
    }

    public function testThrowsWhenBundleExceedsLimit(): void
    {
        $builder = new RenderBundleBuilder('trace');
        $builder->withIndexHtml('<html></html>');

        $this->expectException(BundleSizeExceededException::class);

        $builder->addFile('assets/big.bin', 'x', 50 * 1024 * 1024 + 1);
        $builder->build();
    }

    public function testBuildReturnsRenderBundle(): void
    {
        $builder = new RenderBundleBuilder('trace');

        $bundle = $builder
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
