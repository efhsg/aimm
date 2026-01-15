<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\dto\pdf\RankingReportData;
use app\dto\pdf\RenderedViews;
use app\dto\pdf\ReportData;
use yii\base\View;

/**
 * Renders Yii views to HTML strings for PDF generation.
 */
class ViewRenderer
{
    public function __construct(
        private readonly View $view,
        private readonly string $viewPath,
    ) {
    }

    /**
     * Render all views needed for the PDF.
     */
    public function render(ReportData $data): RenderedViews
    {
        // Render main content
        $contentHtml = $this->view->renderFile(
            $this->viewPath . '/index.php',
            ['reportData' => $data],
        );

        // Wrap in layout
        $indexHtml = $this->view->renderFile(
            $this->viewPath . '/layouts/pdf_main.php',
            ['content' => $contentHtml],
        );

        // Render header
        $headerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_header.php',
            ['reportData' => $data],
        );

        // Render footer
        $footerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_footer.php',
            ['reportData' => $data],
        );

        return new RenderedViews($indexHtml, $headerHtml, $footerHtml);
    }

    /**
     * Render all views needed for the ranking PDF.
     */
    public function renderRanking(RankingReportData $data): RenderedViews
    {
        // Render main content
        $contentHtml = $this->view->renderFile(
            $this->viewPath . '/ranking.php',
            ['reportData' => $data],
        );

        // Wrap in layout
        $indexHtml = $this->view->renderFile(
            $this->viewPath . '/layouts/pdf_main.php',
            ['content' => $contentHtml],
        );

        // Render header (ranking-specific)
        $headerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_ranking_header.php',
            ['reportData' => $data],
        );

        // Render footer (shared)
        $footerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_footer.php',
            ['reportData' => $data],
        );

        return new RenderedViews($indexHtml, $headerHtml, $footerHtml);
    }
}
