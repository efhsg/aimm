<?php

declare(strict_types=1);

namespace tests\unit\handlers\pdf;

use app\dto\pdf\ChartDto;
use app\dto\pdf\CompanyDto;
use app\dto\pdf\FinancialsDto;
use app\dto\pdf\PeerGroupDto;
use app\dto\pdf\RenderBundle;
use app\dto\pdf\RenderedViews;
use app\dto\pdf\ReportData;
use app\handlers\pdf\BundleAssembler;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;

/**
 * @covers \app\handlers\pdf\BundleAssembler
 */
final class BundleAssemblerTest extends Unit
{
    private string $tempDir;
    private string $cssPath;
    private string $fontsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/bundle_assembler_test_' . uniqid();
        $this->cssPath = $this->tempDir . '/css';
        $this->fontsPath = $this->tempDir . '/fonts';

        mkdir($this->cssPath, 0755, true);
        mkdir($this->fontsPath, 0755, true);

        file_put_contents($this->cssPath . '/report.css', '.report { color: black; }');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testAssemblesBasicBundle(): void
    {
        $assembler = new BundleAssembler($this->cssPath, $this->fontsPath);

        $views = new RenderedViews(
            '<div>Main content</div>',
            '<div>Header</div>',
            '<div>Footer</div>',
        );

        $data = $this->createReportData();

        $bundle = $assembler->assemble($views, $data);

        $this->assertInstanceOf(RenderBundle::class, $bundle);
        $this->assertSame('<div>Main content</div>', $bundle->indexHtml);
        $this->assertSame('<div>Header</div>', $bundle->headerHtml);
        $this->assertSame('<div>Footer</div>', $bundle->footerHtml);
    }

    public function testIncludesCssAsset(): void
    {
        $assembler = new BundleAssembler($this->cssPath, $this->fontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportData();

        $bundle = $assembler->assemble($views, $data);

        $this->assertArrayHasKey('assets/report.css', $bundle->files);
        $this->assertSame('.report { color: black; }', $bundle->files['assets/report.css']);
    }

    public function testThrowsExceptionWhenCssMissing(): void
    {
        $emptyCssPath = $this->tempDir . '/empty_css';
        mkdir($emptyCssPath, 0755, true);

        $assembler = new BundleAssembler($emptyCssPath, $this->fontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportData();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSS file not found');

        $assembler->assemble($views, $data);
    }

    public function testIncludesFontsWhenAvailable(): void
    {
        file_put_contents($this->fontsPath . '/inter.woff2', 'font-data');

        $assembler = new BundleAssembler($this->cssPath, $this->fontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportData();

        $bundle = $assembler->assemble($views, $data);

        $this->assertArrayHasKey('assets/fonts/inter.woff2', $bundle->files);
        $this->assertSame('font-data', $bundle->files['assets/fonts/inter.woff2']);
    }

    public function testSkipsFontsWhenDirectoryMissing(): void
    {
        $noFontsPath = $this->tempDir . '/no_fonts';

        $assembler = new BundleAssembler($this->cssPath, $noFontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportData();

        $bundle = $assembler->assemble($views, $data);

        $fontKeys = array_filter(
            array_keys($bundle->files),
            fn (string $key): bool => str_starts_with($key, 'assets/fonts/'),
        );

        $this->assertEmpty($fontKeys);
    }

    public function testIncludesAvailableCharts(): void
    {
        $assembler = new BundleAssembler($this->cssPath, $this->fontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportDataWithCharts();

        $bundle = $assembler->assemble($views, $data);

        $this->assertArrayHasKey('charts/valuation.png', $bundle->files);
        $this->assertSame('PNG-BYTES', $bundle->files['charts/valuation.png']);
    }

    public function testExcludesUnavailableCharts(): void
    {
        $assembler = new BundleAssembler($this->cssPath, $this->fontsPath);

        $views = new RenderedViews('<div>Content</div>', '<div>Header</div>', '<div>Footer</div>');
        $data = $this->createReportData();

        $bundle = $assembler->assemble($views, $data);

        $chartKeys = array_filter(
            array_keys($bundle->files),
            fn (string $key): bool => str_starts_with($key, 'charts/'),
        );

        $this->assertEmpty($chartKeys);
    }

    private function createReportData(): ReportData
    {
        return new ReportData(
            reportId: 'rpt_test',
            traceId: 'trace_123',
            company: new CompanyDto('c1', 'Test Company', 'TEST', 'Technology'),
            financials: new FinancialsDto([]),
            peerGroup: new PeerGroupDto('Tech Peers', ['Company A', 'Company B']),
            charts: [
                ChartDto::placeholder('chart1', 'bar', 'Not available'),
            ],
            generatedAt: new DateTimeImmutable(),
        );
    }

    private function createReportDataWithCharts(): ReportData
    {
        return new ReportData(
            reportId: 'rpt_test',
            traceId: 'trace_123',
            company: new CompanyDto('c1', 'Test Company', 'TEST', 'Technology'),
            financials: new FinancialsDto([]),
            peerGroup: new PeerGroupDto('Tech Peers', ['Company A', 'Company B']),
            charts: [
                ChartDto::withImage('valuation', 'bar', 'PNG-BYTES', 800, 400),
            ],
            generatedAt: new DateTimeImmutable(),
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }
}
