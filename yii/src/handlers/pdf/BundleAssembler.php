<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\dto\pdf\RankingReportData;
use app\dto\pdf\RenderBundle;
use app\dto\pdf\RenderedViews;
use app\dto\pdf\ReportData;
use app\factories\pdf\RenderBundleFactory;
use RuntimeException;

/**
 * Assembles a RenderBundle from rendered views and assets.
 */
class BundleAssembler
{
    public function __construct(
        private readonly string $cssPath,
        private readonly string $fontsPath,
        private readonly string $imagesPath,
    ) {
    }

    /**
     * Assemble a complete render bundle.
     */
    public function assemble(RenderedViews $views, ReportData $data): RenderBundle
    {
        $factory = RenderBundle::factory($data->traceId)
            ->withIndexHtml($views->indexHtml)
            ->withHeaderHtml($views->headerHtml)
            ->withFooterHtml($views->footerHtml);

        // Add CSS
        $this->addCssAssets($factory);

        // Add fonts
        $this->addFontAssets($factory);

        // Add images
        $this->addImageAssets($factory);

        // Add chart images (when available)
        $this->addChartAssets($factory, $data);

        return $factory->build();
    }

    /**
     * Assemble a render bundle for ranking report (no charts).
     */
    public function assembleRanking(RenderedViews $views, RankingReportData $data): RenderBundle
    {
        $factory = RenderBundle::factory($data->traceId)
            ->withIndexHtml($views->indexHtml)
            ->withHeaderHtml($views->headerHtml)
            ->withFooterHtml($views->footerHtml);

        // Add CSS
        $this->addCssAssets($factory);

        // Add fonts
        $this->addFontAssets($factory);

        // Add images
        $this->addImageAssets($factory);

        // No charts for ranking reports

        return $factory->build();
    }

    private function addCssAssets(RenderBundleFactory $factory): void
    {
        $cssFile = $this->cssPath . '/report.css';

        if (!file_exists($cssFile)) {
            throw new RuntimeException("CSS file not found: {$cssFile}");
        }

        $css = file_get_contents($cssFile);

        if ($css === false) {
            throw new RuntimeException("Failed to read CSS file: {$cssFile}");
        }

        $factory->addFile('assets/report.css', $css, strlen($css));
    }

    private function addFontAssets(RenderBundleFactory $factory): void
    {
        if (!is_dir($this->fontsPath)) {
            return; // Fonts are optional
        }

        $fontFiles = glob($this->fontsPath . '/*.woff2');

        if ($fontFiles === false) {
            return;
        }

        foreach ($fontFiles as $fontFile) {
            $fontBytes = file_get_contents($fontFile);

            if ($fontBytes === false) {
                continue;
            }

            $fontName = basename($fontFile);
            $factory->addFile("assets/fonts/{$fontName}", $fontBytes, strlen($fontBytes));
        }
    }

    private function addImageAssets(RenderBundleFactory $factory): void
    {
        if (!is_dir($this->imagesPath)) {
            return;
        }

        // Support SVG and PNG logos
        $logoFiles = [
            'logo.svg',
            'logo.png',
        ];

        foreach ($logoFiles as $file) {
            $fullPath = $this->imagesPath . '/' . $file;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                if ($content !== false) {
                    $factory->addFile("assets/images/{$file}", $content, strlen($content));
                }
            }
        }
    }

    private function addChartAssets(RenderBundleFactory $factory, ReportData $data): void
    {
        foreach ($data->charts as $chart) {
            if (!$chart->available || $chart->pngBytes === null) {
                continue;
            }

            $factory->addFile(
                "charts/{$chart->id}.png",
                $chart->pngBytes,
                strlen($chart->pngBytes),
            );
        }
    }
}
