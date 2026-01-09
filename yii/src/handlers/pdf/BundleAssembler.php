<?php

declare(strict_types=1);

namespace app\handlers\pdf;

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

        // Add chart images (when available)
        $this->addChartAssets($factory, $data);

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
