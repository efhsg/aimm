<?php

declare(strict_types=1);

namespace app\controllers;

use app\adapters\StorageInterface;
use app\enums\PdfJobStatus;
use app\handlers\pdf\PdfGenerationHandler;
use app\queries\PdfJobRepositoryInterface;
use DateTimeImmutable;
use Yii;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Report controller for PDF report preview and generation.
 *
 * Provides:
 * - Preview routes (dev only): /report/preview, /report/preview-full
 * - API routes: /api/reports/generate, /api/jobs/{id}, /api/reports/{reportId}/download
 */
final class ReportController extends Controller
{
    /** @var string|false */
    public $layout = false;

    public function __construct(
        $id,
        $module,
        private readonly PdfJobRepositoryInterface $jobRepository,
        private readonly PdfGenerationHandler $pdfHandler,
        private readonly StorageInterface $storage,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Disable CSRF for API actions
        if (in_array($action->id, ['generate', 'job-status', 'download'], true)) {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

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

    // =========================================================================
    // API Endpoints
    // =========================================================================

    /**
     * Generate a PDF report.
     *
     * POST /api/reports/generate
     * Body: {"reportId": "rpt_...", "options": {...}}
     *
     * @return array{jobId: int}
     */
    public function actionGenerate(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $request = Yii::$app->request;
        $reportId = $request->post('reportId');

        if (empty($reportId)) {
            Yii::$app->response->statusCode = 400;

            return ['error' => 'reportId is required'];
        }

        // Compute stable params hash for idempotency
        $options = $request->post('options', []);
        $paramsHash = $this->computeParamsHash($options);

        // Generate trace ID
        $traceId = sprintf('pdf-%s-%s', date('Ymd-His'), substr(md5(uniqid('', true)), 0, 8));

        // Get requester ID if user component is configured
        $requesterId = null;
        try {
            if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
                $requesterId = (string) Yii::$app->user->id;
            }
        } catch (\Throwable) {
            // User component not configured - leave requesterId as null
        }

        // Create or get existing job
        $job = $this->jobRepository->findOrCreate(
            $reportId,
            $paramsHash,
            $traceId,
            $requesterId,
        );

        // If job is pending, process it synchronously
        if ($job->status === PdfJobStatus::Pending->value) {
            $this->pdfHandler->handle((int) $job->id);

            // Refresh job to get final status
            $job = $this->jobRepository->findById((int) $job->id);
        }

        return ['jobId' => (int) $job->id];
    }

    /**
     * Get job status.
     *
     * GET /api/jobs/{id}
     *
     * @return array{jobId: int, status: string, reportId: string, outputUri: ?string, error: ?array{code: string, message: string}}
     * @throws NotFoundHttpException
     */
    public function actionJobStatus(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $job = $this->jobRepository->findById($id);

        if ($job === null) {
            throw new NotFoundHttpException('Job not found');
        }

        return [
            'jobId' => (int) $job->id,
            'status' => $job->status,
            'reportId' => $job->report_id,
            'outputUri' => $job->output_uri,
            'error' => $job->error_code !== null ? [
                'code' => $job->error_code,
                'message' => $job->error_message,
            ] : null,
        ];
    }

    /**
     * Download the generated PDF.
     *
     * GET /api/reports/{reportId}/download
     *
     * @throws NotFoundHttpException
     */
    public function actionDownload(string $reportId): Response
    {
        $job = $this->jobRepository->findLatestCompleted($reportId);

        if ($job === null || $job->output_uri === null) {
            throw new NotFoundHttpException('PDF not available');
        }

        if (!$this->storage->exists($job->output_uri)) {
            throw new NotFoundHttpException('PDF file not found');
        }

        $filename = basename($job->output_uri);

        return Yii::$app->response->sendFile(
            $job->output_uri,
            $filename,
            ['mimeType' => 'application/pdf'],
        );
    }

    /**
     * Compute a stable hash for request options.
     *
     * @param mixed $options
     */
    private function computeParamsHash(mixed $options): string
    {
        $normalized = $this->normalizeOptions($options);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    /**
     * Recursively normalize options for stable hashing.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeOptions(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        // Check if sequential array (list)
        if (array_values($value) === $value) {
            return array_map([$this, 'normalizeOptions'], $value);
        }

        // Associative array: sort keys
        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeOptions($item);
        }

        return $value;
    }
}
