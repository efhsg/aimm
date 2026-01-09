<?php

declare(strict_types=1);

namespace app\controllers;

use DateTimeImmutable;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Report controller for PDF report preview and generation.
 *
 * The preview action is only available in development mode.
 */
final class ReportController extends Controller
{
    /** @var string|false */
    public $layout = false;

    /**
     * Preview report HTML (dev only).
     *
     * Renders the report template with mock data for visual inspection.
     * This helps iterate on report styling without generating actual PDFs.
     *
     * @throws ForbiddenHttpException if not in development mode
     */
    public function actionPreview(string $reportId = 'demo'): string
    {
        if (!YII_DEBUG) {
            throw new ForbiddenHttpException('Preview only available in development mode');
        }

        $reportData = $this->createMockReportData($reportId);

        return $this->renderPartial('index', [
            'reportData' => $reportData,
        ]);
    }

    /**
     * Preview with full layout including CSS (dev only).
     *
     * Renders the complete HTML document with embedded styles.
     *
     * @throws ForbiddenHttpException if not in development mode
     */
    public function actionPreviewFull(string $reportId = 'demo'): Response
    {
        if (!YII_DEBUG) {
            throw new ForbiddenHttpException('Preview only available in development mode');
        }

        $reportData = $this->createMockReportData($reportId);

        // Render the report content
        $content = $this->renderPartial('index', [
            'reportData' => $reportData,
        ]);

        // Read compiled CSS
        $cssPath = Yii::getAlias('@webroot/css/report.css');
        $css = file_exists($cssPath) ? file_get_contents($cssPath) : '';

        // Build full HTML document with inline CSS
        $html = $this->buildPreviewHtml($content, $css, $reportData);

        return $this->asHtml($html);
    }

    /**
     * Create mock report data for preview.
     *
     * Uses stdClass objects to match the structure expected by templates.
     * In Phase 3, these will be replaced with proper DTOs.
     */
    private function createMockReportData(string $reportId): \stdClass
    {
        return (object) [
            'reportId' => $reportId,
            'traceId' => 'preview-' . time(),
            'company' => (object) [
                'id' => 'c-demo',
                'name' => 'Acme Technologies Inc.',
                'ticker' => 'ACME',
                'industry' => 'Technology',
            ],
            'financials' => (object) [
                'metrics' => [
                    (object) [
                        'label' => 'Revenue',
                        'value' => 1_500_000_000,
                        'change' => 0.12,
                        'peerAverage' => 1_200_000_000,
                        'format' => 'currency',
                    ],
                    (object) [
                        'label' => 'EBITDA',
                        'value' => 375_000_000,
                        'change' => 0.08,
                        'peerAverage' => 288_000_000,
                        'format' => 'currency',
                    ],
                    (object) [
                        'label' => 'EBITDA Margin',
                        'value' => 0.25,
                        'change' => -0.02,
                        'peerAverage' => 0.24,
                        'format' => 'percent',
                    ],
                    (object) [
                        'label' => 'Net Income',
                        'value' => 180_000_000,
                        'change' => 0.15,
                        'peerAverage' => 144_000_000,
                        'format' => 'currency',
                    ],
                    (object) [
                        'label' => 'Net Margin',
                        'value' => 0.12,
                        'change' => 0.01,
                        'peerAverage' => 0.12,
                        'format' => 'percent',
                    ],
                    (object) [
                        'label' => 'ROE',
                        'value' => 0.18,
                        'change' => 0.02,
                        'peerAverage' => 0.15,
                        'format' => 'percent',
                    ],
                    (object) [
                        'label' => 'Debt/Equity',
                        'value' => 0.45,
                        'change' => -0.05,
                        'peerAverage' => 0.55,
                        'format' => 'number',
                    ],
                ],
            ],
            'peerGroup' => (object) [
                'name' => 'Technology Peers',
                'companies' => ['TechCorp', 'InnovateSoft', 'DataDynamics'],
            ],
            'charts' => [], // Empty for preview; charts require analytics service
            'generatedAt' => new DateTimeImmutable(),
        ];
    }

    /**
     * Build complete HTML document for preview.
     */
    private function buildPreviewHtml(string $content, string $css, \stdClass $reportData): string
    {
        $companyName = htmlspecialchars($reportData->company->name ?? 'Report Preview', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$companyName} - Report Preview</title>
    <style>
{$css}

/* Preview-specific styles */
body {
    margin: 20px;
    background: #f5f5f5;
}

.report {
    background: white;
    padding: 25mm 15mm 20mm 15mm;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.preview-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #ff9800;
    color: white;
    text-align: center;
    padding: 8px;
    font-family: system-ui, sans-serif;
    font-size: 14px;
    font-weight: 600;
    z-index: 1000;
}

.preview-banner + body {
    margin-top: 50px;
}
    </style>
</head>
<body>
    <div class="preview-banner">PREVIEW MODE - This is not an actual PDF</div>
    {$content}
</body>
</html>
HTML;
    }

    /**
     * Return response as HTML content type.
     */
    private function asHtml(string $html): Response
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');
        $response->data = $html;

        return $response;
    }
}
