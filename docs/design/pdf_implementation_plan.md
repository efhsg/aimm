# PDF Generation Implementation Plan

**Date:** 2026-01-09
**Status:** Ready for Implementation
**Reference:** [pdf_generation_strategy.md](pdf_generation_strategy.md)

This document provides step-by-step instructions for implementing the PDF generation system as defined in the technical design.

---

## Prerequisites

Before starting, ensure:
- Docker environment is running (`docker-compose up -d`)
- Database migrations are current (`docker exec aimm_yii php yii migrate`)
- You have read and understood `pdf_generation_strategy.md`

---

## Generation Mode Decision (Sync-first)

Default to synchronous generation if p95 end-to-end render time is ≤10s. Switch to queue mode if render time exceeds the budget or concurrency/bursts saturate PHP-FPM/Gotenberg.

- **Config:** `PDF_GENERATION_MODE=sync|queue` (default `sync`)
- **Budget:** `PDF_RENDER_BUDGET_MS=10000` (used for operational monitoring)
- **Measurement:** instrument timings for data fetch, analytics, HTML render, bundle assembly, Gotenberg round-trip, and storage
- **Config wiring:** in `yii/config/params.php`, set:
  - `pdfGenerationMode` from `getenv('PDF_GENERATION_MODE') ?: 'sync'`
  - `pdfRenderBudgetMs` from `getenv('PDF_RENDER_BUDGET_MS') ?: 10000`

**Validation (p50/p95/p99):**
1. Pick 10-20 representative report IDs (small, medium, large, chart-heavy).
2. Run 3-5 generations per report in sync mode to warm caches.
3. Collect per-step timings from logs (or metrics) for total, data fetch, analytics, render, bundle assembly, Gotenberg, store.
4. Compute p50/p95/p99 for total and Gotenberg round-trip; if p95 ≤ 10s, keep sync; else switch to queue.

**Logging shape (required):**
```json
{
  "event": "pdf.render.completed",
  "traceId": "...",
  "jobId": "...",
  "mode": "sync",
  "pdf_bytes": 1234567,
  "memory_peak_kb": 512000,
  "timings_ms": {
    "total": 9123,
    "data_fetch": 820,
    "analytics": 2300,
    "view_render": 410,
    "bundle_assemble": 180,
    "gotenberg": 4700,
    "store": 120
  }
}
```

**Benchmark command (required for validation):**
- **Create** `yii/src/commands/PdfBenchmarkController.php`
- **Usage:** `php yii pdf-benchmark/run --reportIds=ID1,ID2 --runs=5 --mode=sync`
- **Behavior:** for each report ID, invoke the PDF generation handler inline, log `timings_ms`, `pdf_bytes`, `memory_peak_kb`, and output a JSON summary suitable for p50/p95/p99 aggregation.

---

## Phase 1: Infrastructure + Hello World

**Goal:** Gotenberg running, basic PDF generation working via console command.

### 1.1 Docker: Gotenberg Service

**Create** `docker/gotenberg/Dockerfile`:
```dockerfile
FROM gotenberg/gotenberg:8
USER root
RUN apt-get update \
    && apt-get install -y curl \
    && rm -rf /var/lib/apt/lists/*
USER gotenberg
```

**Update** `docker-compose.yml` - add service:
```yaml
services:
  gotenberg:
    container_name: aimm_gotenberg
    build:
      context: ./docker/gotenberg
      dockerfile: Dockerfile
    restart: unless-stopped
    command:
      - "gotenberg"
      - "--api-timeout=30s"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3000/health"]
      interval: 10s
      timeout: 3s
      retries: 5
    environment:
      LOG_LEVEL: info
```

**Verify:**
```bash
docker-compose up -d gotenberg
docker exec aimm_gotenberg curl -s http://localhost:3000/health
# Expected: {"status":"up"}
```

### 1.2 Exceptions

**Create** `yii/src/exceptions/SecurityException.php`:
```php
<?php

declare(strict_types=1);

namespace app\exceptions;

final class SecurityException extends \RuntimeException
{
}
```

**Create** `yii/src/exceptions/BundleSizeExceededException.php`:
```php
<?php

declare(strict_types=1);

namespace app\exceptions;

final class BundleSizeExceededException extends \RuntimeException
{
}
```

### 1.3 DTOs: RenderBundle + PdfOptions

**Create** `yii/src/dto/pdf/PdfOptions.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class PdfOptions
{
    public function __construct(
        public string $paperWidth = '210mm',
        public string $paperHeight = '297mm',
        public string $marginTop = '25mm',
        public string $marginBottom = '20mm',
        public string $marginLeft = '15mm',
        public string $marginRight = '15mm',
        public float $scale = 1.0,
        public bool $landscape = false,
        public bool $printBackground = true,
    ) {}

    /** @return array<string, string> */
    public function toFormFields(): array
    {
        return [
            'paperWidth' => $this->paperWidth,
            'paperHeight' => $this->paperHeight,
            'marginTop' => $this->marginTop,
            'marginBottom' => $this->marginBottom,
            'marginLeft' => $this->marginLeft,
            'marginRight' => $this->marginRight,
            'scale' => (string) $this->scale,
            'landscape' => $this->landscape ? 'true' : 'false',
            'printBackground' => $this->printBackground ? 'true' : 'false',
            'preferCssPageSize' => 'false',
        ];
    }

    public static function standard(): self
    {
        return new self();
    }

    public static function landscape(): self
    {
        return new self(
            paperWidth: '297mm',
            paperHeight: '210mm',
            marginTop: '15mm',
            marginBottom: '15mm',
            marginLeft: '20mm',
            marginRight: '20mm',
            landscape: true,
        );
    }
}
```

**Create** `yii/src/dto/pdf/RenderBundle.php`:
Copy the `RenderBundle` class from `pdf_generation_strategy.md` section 5.1.

**Create** `yii/src/dto/pdf/RenderBundleBuilder.php`:
Copy the `RenderBundleBuilder` class from `pdf_generation_strategy.md` section 5.1.

### 1.4 Client: GotenbergClient

**Create** `yii/src/clients/GotenbergClient.php`:
```php
<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\pdf\PdfOptions;
use app\dto\pdf\RenderBundle;
use app\exceptions\GotenbergException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;

final class GotenbergClient
{
    private const ENDPOINT = '/forms/chromium/convert/html';
    private const CONNECT_TIMEOUT = 2.0;
    private const TIMEOUT = 30.0;

    public function __construct(
        private readonly Client $httpClient,
        private readonly string $baseUrl = 'http://aimm_gotenberg:3000',
    ) {}

    /**
     * @throws GotenbergException
     */
    public function render(RenderBundle $bundle, PdfOptions $options): string
    {
        $multipart = $this->buildMultipart($bundle, $options);

        try {
            $response = $this->httpClient->post($this->baseUrl . self::ENDPOINT, [
                'headers' => [
                    'X-Trace-Id' => $bundle->traceId,
                ],
                'body' => new MultipartStream($multipart),
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($status >= 400) {
                $snippet = substr($body, 0, 2000);
                $retryable = $status >= 500;

                \Yii::error([
                    'message' => 'Gotenberg render failed',
                    'traceId' => $bundle->traceId,
                    'status' => $status,
                    'body' => $snippet,
                ], self::class);

                throw new GotenbergException(
                    "Failed to render PDF (HTTP {$status})",
                    $status,
                    null,
                    retryable: $retryable,
                    statusCode: $status,
                    responseBodySnippet: $snippet,
                );
            }

            return $body;
        } catch (GuzzleException $e) {
            \Yii::error([
                'message' => 'Gotenberg render failed',
                'traceId' => $bundle->traceId,
                'error' => $e->getMessage(),
            ], self::class);

            throw new GotenbergException(
                "Failed to render PDF: {$e->getMessage()}",
                $e->getCode(),
                $e,
                retryable: true,
            );
        }
    }

    /**
     * @return array<int, array{name: string, contents: string|resource, filename?: string}>
     */
    private function buildMultipart(RenderBundle $bundle, PdfOptions $options): array
    {
        $parts = [];

        // PDF options
        foreach ($options->toFormFields() as $name => $value) {
            $parts[] = ['name' => $name, 'contents' => $value];
        }

        // Main HTML
        $parts[] = [
            'name' => 'files',
            'contents' => $bundle->indexHtml,
            'filename' => 'index.html',
        ];

        // Header/Footer
        if ($bundle->headerHtml !== null) {
            $parts[] = [
                'name' => 'files',
                'contents' => $bundle->headerHtml,
                'filename' => 'header.html',
            ];
        }

        if ($bundle->footerHtml !== null) {
            $parts[] = [
                'name' => 'files',
                'contents' => $bundle->footerHtml,
                'filename' => 'footer.html',
            ];
        }

        // Assets
        foreach ($bundle->files as $path => $content) {
            $parts[] = [
                'name' => 'files',
                'contents' => is_resource($content) ? $content : $content,
                'filename' => $path,
            ];
        }

        return $parts;
    }
}
```

**Create** `yii/src/exceptions/GotenbergException.php`:
```php
<?php

declare(strict_types=1);

namespace app\exceptions;

final class GotenbergException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly bool $retryable = false,
        public readonly ?int $statusCode = null,
        public readonly ?string $responseBodySnippet = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

### 1.5 Console Command: Hello World

**Create** `yii/src/commands/PdfController.php`:
```php
<?php

declare(strict_types=1);

namespace app\commands;

use app\clients\GotenbergClient;
use app\dto\pdf\PdfOptions;
use app\dto\pdf\RenderBundle;
use yii\console\Controller;
use yii\console\ExitCode;

final class PdfController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly GotenbergClient $gotenbergClient,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Generate a test PDF to verify Gotenberg integration.
     */
    public function actionTest(): int
    {
        $traceId = sprintf('test-%s', date('YmdHis'));

        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test PDF</title>
    <link rel="stylesheet" href="assets/test.css">
</head>
<body>
    <h1>Hello from Gotenberg!</h1>
    <p>Generated at: <?= date('Y-m-d H:i:s') ?></p>
</body>
</html>
HTML;

        $css = <<<'CSS'
body { font-family: sans-serif; padding: 20mm; }
h1 { color: #333; }
CSS;

        $bundle = RenderBundle::builder($traceId)
            ->withIndexHtml($html)
            ->addFile('assets/test.css', $css, strlen($css))
            ->build();

        $this->stdout("Generating test PDF with traceId: {$traceId}\n");

        try {
            $pdfBytes = $this->gotenbergClient->render($bundle, PdfOptions::standard());

            $outputPath = \Yii::getAlias('@runtime') . "/test-{$traceId}.pdf";
            file_put_contents($outputPath, $pdfBytes);

            $this->stdout("PDF generated: {$outputPath}\n");
            $this->stdout("Size: " . strlen($pdfBytes) . " bytes\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("Error: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
```

### 1.6 DI Configuration

**Update** `yii/config/container.php` - add bindings:
```php
use app\clients\GotenbergClient;
use GuzzleHttp\Client;

// Add to container definitions:
GotenbergClient::class => static function () {
    $baseUrl = \Yii::$app->params['gotenbergBaseUrl'] ?? 'http://aimm_gotenberg:3000';

    return new GotenbergClient(
        new Client(),
        $baseUrl,
    );
},
```

**Update** `yii/config/params.php`:
```php
'gotenbergBaseUrl' => getenv('GOTENBERG_BASE_URL') ?: 'http://aimm_gotenberg:3000',
```

### Phase 1 Verification

```bash
# Rebuild containers
docker-compose up -d --build

# Run test command
docker exec aimm_yii php yii pdf/test

# Check output
ls -la yii/runtime/test-*.pdf
```

**Expected:** PDF file created in runtime directory.

---

## Phase 2: Templating Foundation

**Goal:** SCSS build pipeline, report templates, dev preview route.

### 2.1 SCSS Build Pipeline

**Create** `yii/package.json`:
```json
{
  "name": "aimm-yii",
  "private": true,
  "scripts": {
    "build:css": "sass web/scss/report.scss web/css/report.css --style=compressed",
    "watch:css": "sass web/scss/report.scss web/css/report.css --watch"
  },
  "devDependencies": {
    "sass": "^1.70.0"
  }
}
```

**Create** `yii/web/scss/_tokens.scss`:
```scss
// Design tokens
$color-text: #1a1a1a;
$color-text-muted: #666;
$color-border: #e0e0e0;
$color-background: #fff;
$color-accent: #0066cc;

$font-sans: 'Franklin Gothic', 'Helvetica Neue', sans-serif;
$font-mono: 'Consolas', monospace;

$spacing-xs: 4px;
$spacing-sm: 8px;
$spacing-md: 16px;
$spacing-lg: 24px;
$spacing-xl: 32px;

$page-margin: 15mm;
```

**Create** `yii/web/scss/_tables.scss`:
```scss
.report-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 10pt;

  thead {
    display: table-header-group; // Repeat on page breaks
  }

  th, td {
    padding: $spacing-sm $spacing-md;
    border-bottom: 1px solid $color-border;
    text-align: left;
  }

  th {
    font-weight: 600;
    background: #f5f5f5;
  }

  // Numeric columns
  .col-numeric {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }

  // Fixed column widths
  .col-year { width: 15mm; }
  .col-amount { width: 25mm; }
  .col-percent { width: 18mm; }

  tbody tr {
    break-inside: avoid;
    page-break-inside: avoid;
  }
}
```

**Create** `yii/web/scss/_print.scss`:
```scss
@page {
  size: A4;
  margin: 25mm 15mm 20mm 15mm;
}

@media print {
  body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .no-print {
    display: none !important;
  }

  .page-break {
    page-break-after: always;
  }

  .avoid-break {
    break-inside: avoid;
    page-break-inside: avoid;
  }
}
```

**Create** `yii/web/scss/report.scss`:
```scss
@use 'tokens';
@use 'tables';
@use 'print';

* {
  box-sizing: border-box;
}

body {
  font-family: tokens.$font-sans;
  font-size: 11pt;
  line-height: 1.5;
  color: tokens.$color-text;
  background: tokens.$color-background;
  margin: 0;
  padding: 0;
}

.report {
  max-width: 210mm;
  margin: 0 auto;

  &__header {
    margin-bottom: tokens.$spacing-xl;
  }

  &__title {
    font-size: 24pt;
    font-weight: 700;
    margin: 0 0 tokens.$spacing-md;
  }

  &__subtitle {
    font-size: 14pt;
    color: tokens.$color-text-muted;
    margin: 0;
  }

  &__section {
    margin-bottom: tokens.$spacing-xl;

    &-title {
      font-size: 14pt;
      font-weight: 600;
      border-bottom: 2px solid tokens.$color-accent;
      padding-bottom: tokens.$spacing-sm;
      margin-bottom: tokens.$spacing-md;
    }
  }

  &__chart {
    width: 100%;
    height: auto;
    margin: tokens.$spacing-md 0;
  }
}
```

**Install and build:**
```bash
cd yii && npm install && npm run build:css
```

### 2.2 Report Views

**Create** `yii/src/views/report/layouts/pdf_main.php`:
```php
<?php
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analysis Report</title>
    <link rel="stylesheet" href="assets/report.css">
</head>
<body>
    <?= $content ?>
</body>
</html>
```

**Create** `yii/src/views/report/partials/_header.php`:
```php
<?php
/** @var app\dto\pdf\ReportData $reportData */
?>
<header style="font-size: 9pt; color: #666; padding: 5mm 15mm; border-bottom: 1px solid #e0e0e0;">
    <span><?= htmlspecialchars($reportData->company->name) ?></span>
    <span style="float: right;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
</header>
```

**Create** `yii/src/views/report/partials/_footer.php`:
```php
<?php
/** @var app\dto\pdf\ReportData $reportData */
?>
<footer style="font-size: 8pt; color: #999; padding: 5mm 15mm; text-align: center;">
    Generated <?= $reportData->generatedAt->format('Y-m-d H:i') ?> UTC | Confidential
</footer>
```

**Create** `yii/src/views/report/partials/_financials.php`:
```php
<?php
/** @var app\dto\pdf\FinancialsDto $financials */
?>
<section class="report__section">
    <h2 class="report__section-title">Financial Summary</h2>
    <table class="report-table">
        <thead>
            <tr>
                <th>Metric</th>
                <th class="col-numeric">Latest</th>
                <th class="col-numeric">YoY Change</th>
                <th class="col-numeric">Peer Avg</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($financials->metrics as $metric): ?>
            <tr>
                <td><?= htmlspecialchars($metric->label) ?></td>
                <td class="col-numeric"><?= $metric->formatValue() ?></td>
                <td class="col-numeric"><?= $metric->formatChange() ?></td>
                <td class="col-numeric"><?= $metric->formatPeerAverage() ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
```

**Create** `yii/src/views/report/partials/_charts.php`:
```php
<?php
/** @var app\dto\pdf\ChartDto[] $charts */
?>
<section class="report__section">
    <h2 class="report__section-title">Visual Analysis</h2>
    <?php foreach ($charts as $chart): ?>
        <figure class="avoid-break">
            <img
                src="charts/<?= htmlspecialchars($chart->id) ?>.png"
                alt="<?= htmlspecialchars($chart->type) ?> chart"
                class="report__chart"
            >
        </figure>
    <?php endforeach; ?>
</section>
```

**Create** `yii/src/views/report/index.php`:
```php
<?php
/** @var app\dto\pdf\ReportData $reportData */
$this->context->layout = 'layouts/pdf_main';
?>
<div class="report">
    <header class="report__header">
        <h1 class="report__title"><?= htmlspecialchars($reportData->company->name) ?></h1>
        <p class="report__subtitle">
            <?= htmlspecialchars($reportData->company->industry) ?> |
            Analysis as of <?= $reportData->generatedAt->format('F j, Y') ?>
        </p>
    </header>

    <?= $this->render('partials/_financials', ['financials' => $reportData->financials]) ?>

    <?php if (!empty($reportData->charts)): ?>
        <?= $this->render('partials/_charts', ['charts' => $reportData->charts]) ?>
    <?php endif; ?>
</div>
```

### 2.3 Preview Controller

**Create** `yii/src/controllers/ReportController.php`:
```php
<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\pdf\ReportData;
use yii\web\Controller;
use yii\web\Response;

final class ReportController extends Controller
{
    public $layout = false;

    /**
     * Preview report HTML (dev only).
     */
    public function actionPreview(string $reportId = 'demo'): Response
    {
        if (!YII_DEBUG) {
            throw new \yii\web\ForbiddenHttpException('Preview only available in dev mode');
        }

        // Mock data for preview
        $reportData = $this->createMockReportData($reportId);

        return $this->render('index', [
            'reportData' => $reportData,
        ]);
    }

    private function createMockReportData(string $reportId): ReportData
    {
        // Create mock DTOs for preview
        // Implementation depends on your DTO structure
        return new ReportData(
            reportId: $reportId,
            traceId: 'preview-' . time(),
            company: new \app\dto\pdf\CompanyDto(
                id: '1',
                name: 'Example Corp',
                ticker: 'EXMP',
                industry: 'Technology',
            ),
            financials: new \app\dto\pdf\FinancialsDto(metrics: []),
            peerGroup: new \app\dto\pdf\PeerGroupDto(name: 'Tech Peers', companies: []),
            charts: [],
            generatedAt: new \DateTimeImmutable(),
        );
    }
}
```

### Phase 2 Verification

```bash
# Build CSS
cd yii && npm run build:css

# Access preview in browser
# http://localhost:8080/report/preview

# Check CSS output
cat yii/web/css/report.css | head -20
```

**Expected:** Preview route renders HTML report with styling.

---

## Phase 3: Data Integration + Persistence

**Goal:** Jobs table, DTOs, handlers, storage, API endpoints.

### 3.1 Database Migration: Jobs Table

The `jobs` table is required in both sync and queue modes for idempotency, auditing, and cacheability.

**Create** `yii/migrations/m260110_000000_create_jobs_table.php`:
```php
<?php

declare(strict_types=1);

use yii\db\Migration;

final class m260110_000000_create_jobs_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%jobs}}', [
            'id' => $this->char(36)->notNull(),
            'report_id' => $this->char(36)->notNull(),
            'params_hash' => $this->char(64)->notNull(),
            'requester_id' => $this->char(36)->notNull(),
            'status' => "ENUM('queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'queued'",
            'trace_id' => $this->char(36)->notNull(),
            'output_uri' => $this->string(500)->null(),
            'error_code' => $this->string(50)->null(),
            'error_message' => $this->text()->null(),
            'attempts' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            'finished_at' => $this->timestamp()->null(),
            'PRIMARY KEY (id)',
        ]);

        // Idempotency index
        $this->createIndex(
            'idx_jobs_report_params',
            '{{%jobs}}',
            ['report_id', 'params_hash'],
            true
        );

        // Status lookups
        $this->createIndex('idx_jobs_status', '{{%jobs}}', 'status');

        // Cleanup queries
        $this->createIndex('idx_jobs_finished', '{{%jobs}}', ['status', 'finished_at']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%jobs}}');
    }
}
```

**Run migration:**
```bash
docker exec aimm_yii php yii migrate
```

### 3.2 PDF DTOs

**Create** `yii/src/dto/pdf/ReportData.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class ReportData
{
    public function __construct(
        public string $reportId,
        public string $traceId,
        public CompanyDto $company,
        public FinancialsDto $financials,
        public PeerGroupDto $peerGroup,
        /** @var ChartDto[] */
        public array $charts,
        public \DateTimeImmutable $generatedAt,
    ) {}
}
```

**Create** `yii/src/dto/pdf/CompanyDto.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class CompanyDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $ticker,
        public string $industry,
    ) {}
}
```

**Create** `yii/src/dto/pdf/FinancialsDto.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class FinancialsDto
{
    public function __construct(
        /** @var MetricRowDto[] */
        public array $metrics,
    ) {}
}
```

**Create** `yii/src/dto/pdf/MetricRowDto.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class MetricRowDto
{
    public function __construct(
        public string $label,
        public ?float $value,
        public ?float $change,
        public ?float $peerAverage,
        public string $format = 'number', // number, currency, percent
    ) {}

    public function formatValue(): string
    {
        return $this->formatNumber($this->value);
    }

    public function formatChange(): string
    {
        if ($this->change === null) {
            return '-';
        }
        $sign = $this->change >= 0 ? '+' : '';
        return $sign . number_format($this->change * 100, 1) . '%';
    }

    public function formatPeerAverage(): string
    {
        return $this->formatNumber($this->peerAverage);
    }

    private function formatNumber(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return match ($this->format) {
            'currency' => '$' . number_format($value / 1_000_000, 1) . 'M',
            'percent' => number_format($value * 100, 1) . '%',
            default => number_format($value, 2),
        };
    }
}
```

**Create** `yii/src/dto/pdf/PeerGroupDto.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class PeerGroupDto
{
    public function __construct(
        public string $name,
        /** @var string[] */
        public array $companies,
    ) {}
}
```

**Create** `yii/src/dto/pdf/ChartDto.php`:
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class ChartDto
{
    public function __construct(
        public string $id,
        public string $type,
        public string $pngBytes,
        public int $width,
        public int $height,
        public int $dpi = 144,
    ) {}
}
```

### 3.3 Storage Interface + Adapter

**Create** `yii/src/adapters/StorageInterface.php`:
```php
<?php

declare(strict_types=1);

namespace app\adapters;

use Psr\Http\Message\StreamInterface;

interface StorageInterface
{
    public function store(string $bytes, string $filename): string;
    public function delete(string $uri): void;
    public function stream(string $uri): StreamInterface;
    public function exists(string $uri): bool;
}
```

**Create** `yii/src/adapters/LocalStorageAdapter.php`:
```php
<?php

declare(strict_types=1);

namespace app\adapters;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

final class LocalStorageAdapter implements StorageInterface
{
    public function __construct(
        private readonly string $basePath,
    ) {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function store(string $bytes, string $filename): string
    {
        $path = $this->basePath . '/' . $filename;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $bytes);

        return $path;
    }

    public function delete(string $uri): void
    {
        if (file_exists($uri)) {
            unlink($uri);
        }
    }

    public function stream(string $uri): StreamInterface
    {
        if (!file_exists($uri)) {
            throw new \RuntimeException("File not found: {$uri}");
        }

        return Utils::streamFor(fopen($uri, 'rb'));
    }

    public function exists(string $uri): bool
    {
        return file_exists($uri);
    }
}
```

### 3.4 Job Repository

**Create** `yii/src/queries/JobRepository.php`:
```php
<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

final class JobRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    public function findOrCreate(
        string $reportId,
        string $paramsHash,
        string $requesterId,
        string $traceId,
    ): array {
        // Try insert, return existing on conflict
        $sql = <<<'SQL'
            INSERT INTO jobs (id, report_id, params_hash, requester_id, trace_id, status)
            VALUES (:id, :reportId, :paramsHash, :requesterId, :traceId, 'queued')
            ON DUPLICATE KEY UPDATE id = id
        SQL;

        $id = $this->generateUuid();

        $this->db->createCommand($sql, [
            ':id' => $id,
            ':reportId' => $reportId,
            ':paramsHash' => $paramsHash,
            ':requesterId' => $requesterId,
            ':traceId' => $traceId,
        ])->execute();

        // Fetch the job (either new or existing)
        return $this->db->createCommand(
            'SELECT * FROM jobs WHERE report_id = :reportId AND params_hash = :paramsHash',
            [':reportId' => $reportId, ':paramsHash' => $paramsHash]
        )->queryOne();
    }

    public function findAndLock(string $jobId): ?array
    {
        // Caller must be inside an active DB transaction.
        return $this->db->createCommand(
            'SELECT * FROM jobs WHERE id = :id FOR UPDATE',
            [':id' => $jobId]
        )->queryOne() ?: null;
    }

    public function transitionTo(string $jobId, string $fromStatus, string $toStatus): bool
    {
        $result = $this->db->createCommand(
            'UPDATE jobs SET status = :toStatus WHERE id = :id AND status = :fromStatus',
            [':id' => $jobId, ':fromStatus' => $fromStatus, ':toStatus' => $toStatus]
        )->execute();

        return $result > 0;
    }

    public function complete(string $jobId, string $outputUri): void
    {
        $this->db->createCommand(
            'UPDATE jobs SET status = :status, output_uri = :uri, finished_at = NOW() WHERE id = :id',
            [':id' => $jobId, ':status' => 'completed', ':uri' => $outputUri]
        )->execute();
    }

    public function fail(string $jobId, string $errorCode, string $errorMessage): void
    {
        $this->db->createCommand(
            'UPDATE jobs SET status = :status, error_code = :code, error_message = :msg, finished_at = NOW() WHERE id = :id',
            [':id' => $jobId, ':status' => 'failed', ':code' => $errorCode, ':msg' => $errorMessage]
        )->execute();
    }

    public function incrementAttempts(string $jobId): void
    {
        $this->db->createCommand(
            'UPDATE jobs SET attempts = attempts + 1 WHERE id = :id',
            [':id' => $jobId]
        )->execute();
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
```

### 3.5 Handlers

**Create** `yii/src/handlers/pdf/ViewRenderer.php`:
```php
<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\dto\pdf\ReportData;
use yii\base\View;

final class ViewRenderer
{
    public function __construct(
        private readonly View $view,
        private readonly string $viewPath,
    ) {}

    public function render(ReportData $data): RenderedViews
    {
        $indexHtml = $this->view->renderFile(
            $this->viewPath . '/index.php',
            ['reportData' => $data]
        );

        $headerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_header.php',
            ['reportData' => $data]
        );

        $footerHtml = $this->view->renderFile(
            $this->viewPath . '/partials/_footer.php',
            ['reportData' => $data]
        );

        return new RenderedViews($indexHtml, $headerHtml, $footerHtml);
    }
}
```

**Create** `yii/src/handlers/pdf/RenderedViews.php`:
```php
<?php

declare(strict_types=1);

namespace app\handlers\pdf;

final readonly class RenderedViews
{
    public function __construct(
        public string $indexHtml,
        public string $headerHtml,
        public string $footerHtml,
    ) {}
}
```

**Create** `yii/src/handlers/pdf/BundleAssembler.php`:
```php
<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\dto\pdf\RenderBundle;
use app\dto\pdf\ReportData;

final class BundleAssembler
{
    public function __construct(
        private readonly string $cssPath,
        private readonly string $fontsPath,
    ) {}

    public function assemble(RenderedViews $views, ReportData $data): RenderBundle
    {
        $builder = RenderBundle::builder($data->traceId)
            ->withIndexHtml($views->indexHtml)
            ->withHeaderHtml($views->headerHtml)
            ->withFooterHtml($views->footerHtml);

        // Add CSS
        $css = file_get_contents($this->cssPath . '/report.css');
        $builder->addFile('assets/report.css', $css, strlen($css));

        // Add fonts
        foreach (glob($this->fontsPath . '/*.woff2') as $fontFile) {
            $fontBytes = file_get_contents($fontFile);
            $fontName = basename($fontFile);
            $builder->addFile("assets/fonts/{$fontName}", $fontBytes, strlen($fontBytes));
        }

        // Add charts
        foreach ($data->charts as $chart) {
            $builder->addFile(
                "charts/{$chart->id}.png",
                $chart->pngBytes,
                strlen($chart->pngBytes)
            );
        }

        return $builder->build();
    }
}
```

**Create** `yii/src/handlers/pdf/PdfGenerationHandler.php`:
```php
<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\adapters\StorageInterface;
use app\clients\GotenbergClient;
use app\dto\pdf\PdfOptions;
use app\factories\ReportDataFactory;
use app\queries\JobRepository;
use app\queries\ReportQuery;
use Psr\Log\LoggerInterface;
use yii\db\Connection;

final class PdfGenerationHandler
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly ReportQuery $reportQuery,
        private readonly ReportDataFactory $reportDataFactory,
        private readonly ViewRenderer $viewRenderer,
        private readonly BundleAssembler $bundleAssembler,
        private readonly GotenbergClient $gotenbergClient,
        private readonly StorageInterface $storage,
        private readonly LoggerInterface $logger,
        private readonly Connection $db,
    ) {}

    public function handle(string $jobId): void
    {
        $transaction = $this->db->beginTransaction();

        try {
            $job = $this->jobRepository->findAndLock($jobId);

            if ($job === null) {
                $this->logger->warning('Job not found', ['jobId' => $jobId]);
                $transaction->rollBack();
                return;
            }

            if ($job['status'] !== 'queued') {
                $this->logger->info('Job not in queued status', ['jobId' => $jobId, 'status' => $job['status']]);
                $transaction->rollBack();
                return;
            }

            if (!$this->jobRepository->transitionTo($jobId, 'queued', 'processing')) {
                $this->logger->info('Failed to acquire job', ['jobId' => $jobId]);
                $transaction->rollBack();
                return;
            }

            $this->jobRepository->incrementAttempts($jobId);
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        try {
            $this->process($job);
        } catch (\Throwable $e) {
            $this->handleFailure($jobId, $job, $e);
        }
    }

    private function process(array $job): void
    {
        $jobId = $job['id'];
        $traceId = $job['trace_id'];

        $this->logger->info('Processing PDF job', ['jobId' => $jobId, 'traceId' => $traceId]);

        // 1. Fetch data
        $queryResult = $this->reportQuery->execute($job['report_id']);

        // 2. Transform to DTO
        $reportData = $this->reportDataFactory->create($queryResult, $traceId);

        // 3. Render views
        $renderedViews = $this->viewRenderer->render($reportData);

        // 4. Assemble bundle
        $bundle = $this->bundleAssembler->assemble($renderedViews, $reportData);

        // 5. Generate PDF
        $pdfBytes = $this->gotenbergClient->render($bundle, PdfOptions::standard());

        // 6. Store
        $filename = sprintf('reports/%s/%s.pdf', date('Y/m'), $jobId);
        $uri = $this->storage->store($pdfBytes, $filename);

        // 7. Complete
        $this->jobRepository->complete($jobId, $uri);

        $this->logger->info('PDF job completed', ['jobId' => $jobId, 'uri' => $uri]);
    }

    private function handleFailure(string $jobId, array $job, \Throwable $e): void
    {
        $this->logger->error('PDF generation failed', [
            'jobId' => $jobId,
            'traceId' => $job['trace_id'],
            'error' => $e->getMessage(),
            'attempts' => $job['attempts'] + 1,
        ]);

        $attempts = (int) $job['attempts'] + 1;
        $isRetryable = $this->isRetryable($e);

        $mode = \Yii::$app->params['pdfGenerationMode'] ?? 'sync';

        if ($isRetryable && $attempts < self::MAX_ATTEMPTS && $mode === 'queue') {
            // Return to queue for retry (queue mode only)
            $this->jobRepository->transitionTo($jobId, 'processing', 'queued');
        } else {
            // Final failure
            $errorCode = $this->classifyError($e);
            $this->jobRepository->fail($jobId, $errorCode, $e->getMessage());
        }
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e instanceof \app\exceptions\GotenbergException) {
            return $e->retryable;
        }

        // Network errors, timeouts are retryable
        // Validation errors, security exceptions are not
        return !($e instanceof \app\exceptions\SecurityException
            || $e instanceof \app\exceptions\BundleSizeExceededException
            || $e instanceof \InvalidArgumentException);
    }

    private function classifyError(\Throwable $e): string
    {
        return match (true) {
            $e instanceof \app\exceptions\SecurityException => 'SECURITY_VIOLATION',
            $e instanceof \app\exceptions\BundleSizeExceededException => 'BUNDLE_TOO_LARGE',
            $e instanceof \app\exceptions\GotenbergException && $e->statusCode !== null && $e->statusCode < 500 => 'GOTENBERG_4XX',
            $e instanceof \app\exceptions\GotenbergException && $e->statusCode !== null => 'GOTENBERG_5XX',
            $e instanceof \app\exceptions\GotenbergException => 'GOTENBERG_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }
}
```

**Dev-only debug artifacts (required):** when `YII_DEBUG` and a failure occurs after bundle assembly, dump to `runtime/debug/pdf_failure_{traceId}/` with:
- `manifest.json` (traceId, jobId, generation mode, totalBytes, file list with bytes + sha256, `timings_ms` summary, `pdf_bytes`, `memory_peak_kb`, versions: app git SHA, gotenberg image tag, PHP version)
- `error.json` (exception class/message/stack, retryable flag, HTTP status/body snippet if available)
- `pdf_options.json` (exact form fields)
- `index.html`, `header.html`, `footer.html`, `assets/`, `charts/`

### 3.6 API Endpoints

**Update** `yii/src/controllers/ReportController.php` - add API actions:
```php
// Add to existing ReportController:

/**
 * POST /api/reports/generate
 */
public function actionGenerate(): array
{
    $request = \Yii::$app->request;
    $reportId = $request->post('reportId');
    $userId = \Yii::$app->user->id; // Authorization checks are out of scope here.

    // Compute params hash
    $options = $this->normalizeOptions($request->post('options', []));
    $paramsHash = hash('sha256', json_encode($options));

    $traceId = sprintf('pdf-%s-%s', date('Ymd'), substr(md5(uniqid()), 0, 8));

    $job = $this->jobRepository->findOrCreate($reportId, $paramsHash, $userId, $traceId);

    if ($job['status'] === 'queued') {
        $mode = \Yii::$app->params['pdfGenerationMode'] ?? 'sync';

        if ($mode === 'sync') {
            /** @var \app\handlers\pdf\PdfGenerationHandler $handler */
            $handler = \Yii::$container->get(\app\handlers\pdf\PdfGenerationHandler::class);
            $handler->handle($job['id']);
        } else {
            \Yii::$app->queue->push(new \app\jobs\PdfGenerationJob(['jobId' => $job['id']]));
        }
    }

    return ['jobId' => $job['id']];
}

/**
 * GET /api/jobs/{id}
 */
public function actionJobStatus(string $id): array
{
    $job = $this->jobRepository->findById($id);

    if ($job === null) {
        throw new \yii\web\NotFoundHttpException('Job not found');
    }

    return [
        'jobId' => $job['id'],
        'status' => $job['status'],
        'reportId' => $job['report_id'],
        'outputUri' => $job['output_uri'],
        'error' => $job['error_code'] ? [
            'code' => $job['error_code'],
            'message' => $job['error_message'],
        ] : null,
    ];
}

/**
 * GET /api/reports/{reportId}/download
 */
public function actionDownload(string $reportId): \yii\web\Response
{
    $job = $this->jobRepository->findLatestCompleted($reportId);

    if ($job === null || $job['output_uri'] === null) {
        throw new \yii\web\NotFoundHttpException('PDF not available');
    }

    $stream = $this->storage->stream($job['output_uri']);

    return \Yii::$app->response->sendStreamAsFile(
        $stream,
        basename($job['output_uri']),
        ['mimeType' => 'application/pdf']
    );
}

private function normalizeOptions(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $isList = array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        return array_map([$this, __FUNCTION__], $value);
    }

    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = $this->normalizeOptions($item);
    }

    return $value;
}
```

### 3.7 Queue Job (queue mode only)

**Create** `yii/src/jobs/PdfGenerationJob.php`:
```php
<?php

declare(strict_types=1);

namespace app\jobs;

use app\handlers\pdf\PdfGenerationHandler;
use yii\base\BaseObject;
use yii\queue\JobInterface;
use yii\queue\Queue;

final class PdfGenerationJob extends BaseObject implements JobInterface
{
    public string $jobId;

    public function execute($queue): void
    {
        /** @var PdfGenerationHandler $handler */
        $handler = \Yii::$container->get(PdfGenerationHandler::class);
        $handler->handle($this->jobId);
    }
}
```

### 3.8 DI Configuration Update

**Update** `yii/config/container.php` - add all new bindings:
```php
use app\adapters\LocalStorageAdapter;
use app\adapters\StorageInterface;
use app\clients\GotenbergClient;
use app\factories\ReportDataFactory;
use app\handlers\pdf\BundleAssembler;
use app\handlers\pdf\PdfGenerationHandler;
use app\handlers\pdf\ViewRenderer;
use app\queries\JobRepository;
use app\queries\ReportQuery;

// Storage
StorageInterface::class => static function () {
    return new LocalStorageAdapter(\Yii::getAlias('@runtime/pdf-storage'));
},

// Gotenberg
GotenbergClient::class => static function () {
    $baseUrl = \Yii::$app->params['gotenbergBaseUrl'] ?? 'http://aimm_gotenberg:3000';

    return new GotenbergClient(
        new \GuzzleHttp\Client(),
        $baseUrl,
    );
},

// PDF Handlers
ViewRenderer::class => static function () {
    return new ViewRenderer(
        new \yii\base\View(),
        \Yii::getAlias('@app/views/report'),
    );
},

BundleAssembler::class => static function () {
    return new BundleAssembler(
        \Yii::getAlias('@webroot/css'),
        \Yii::getAlias('@webroot/fonts'),
    );
},

JobRepository::class => static function () {
    return new JobRepository(\Yii::$app->db);
},

PdfGenerationHandler::class => static function () {
    return new PdfGenerationHandler(
        \Yii::$container->get(JobRepository::class),
        \Yii::$container->get(ReportQuery::class),
        \Yii::$container->get(ReportDataFactory::class),
        \Yii::$container->get(ViewRenderer::class),
        \Yii::$container->get(BundleAssembler::class),
        \Yii::$container->get(GotenbergClient::class),
        \Yii::$container->get(StorageInterface::class),
        \Yii::getLogger(),
        \Yii::$app->db,
    );
},
```

### 3.9 Queue Configuration (queue mode only)

**Update** `yii/config/console.php` - add queue component:
```php
'components' => [
    'queue' => [
        'class' => \yii\queue\redis\Queue::class,
        'redis' => 'redis',
        'channel' => 'pdf-queue',
    ],
    'redis' => [
        'class' => \yii\redis\Connection::class,
        'hostname' => 'aimm_redis',
        'port' => 6379,
    ],
],
```

### Phase 3 Verification

```bash
# Run migration
docker exec aimm_yii php yii migrate

# Test API endpoint
curl -X POST http://localhost:8080/api/reports/generate \
  -H "Content-Type: application/json" \
  -d '{"reportId": "test-123"}'

# Check job status
curl http://localhost:8080/api/jobs/{jobId}

# Run queue worker (queue mode only)
docker exec aimm_yii php yii queue/listen
```

**Expected:** Job created, PDF stored, status updated to completed. In sync mode this happens during the request; in queue mode it completes after the worker runs.

---

## Phase 4: Charts + Regression Testing

**Goal:** Analytics integration, chart caching, golden master tests.

### 4.1 Analytics Service Contract

**Create** `yii/src/clients/AnalyticsClient.php`:
```php
<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\pdf\ChartDto;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use yii\caching\CacheInterface;

final class AnalyticsClient
{
    private const CACHE_TTL = 3600; // 1 hour
    private const TIMEOUT = 15.0;

    public function __construct(
        private readonly Client $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl = 'http://aimm_analytics:5000',
    ) {}

    public function generateChart(string $type, array $data): ChartDto
    {
        $cacheKey = 'chart:' . hash('sha256', json_encode([$type, $data]));

        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $response = $this->httpClient->post("{$this->baseUrl}/charts/generate", [
                'json' => ['type' => $type, 'data' => $data],
                'timeout' => self::TIMEOUT,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            $chart = new ChartDto(
                id: $result['id'],
                type: $type,
                pngBytes: base64_decode($result['png']),
                width: $result['width'],
                height: $result['height'],
                dpi: $result['dpi'] ?? 144,
            );

            $this->cache->set($cacheKey, $chart, self::CACHE_TTL);

            return $chart;
        } catch (\Throwable $e) {
            $this->logger->error('Chart generation failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw new AnalyticsException("Failed to generate chart: {$e->getMessage()}", 0, $e);
        }
    }
}
```

### 4.2 Golden Master Testing

**Create** `yii/tests/unit/handlers/pdf/PdfGenerationHandlerTest.php`:
```php
<?php

declare(strict_types=1);

namespace tests\unit\handlers\pdf;

use app\handlers\pdf\PdfGenerationHandler;
use Codeception\Test\Unit;

final class PdfGenerationHandlerTest extends Unit
{
    public function testGeneratesValidPdfForCompleteReport(): void
    {
        // Test with fixture data
        $this->markTestIncomplete('Implement with golden master comparison');
    }

    public function testFailsGracefullyOnMissingData(): void
    {
        // Test error handling
        $this->markTestIncomplete('Implement error scenario test');
    }
}
```

**Create** `yii/tests/_data/pdf_fixtures/sample_report.json`:
```json
{
  "reportId": "fixture-001",
  "company": {
    "id": "c-001",
    "name": "Sample Corp",
    "ticker": "SMPL",
    "industry": "Technology"
  },
  "financials": {
    "metrics": [
      {"label": "Revenue", "value": 1500000000, "change": 0.12, "peerAverage": 1200000000, "format": "currency"},
      {"label": "EBITDA Margin", "value": 0.25, "change": 0.02, "peerAverage": 0.22, "format": "percent"}
    ]
  }
}
```

### Phase 4 Verification

```bash
# Run unit tests
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/handlers/pdf/

# Generate golden master
docker exec aimm_yii php yii pdf/generate-golden --fixture=sample_report

# Compare with golden master
docker exec aimm_yii php yii pdf/compare-golden --fixture=sample_report --fuzz=5
```

---

## Post-Implementation Cleanup

### Remove Old Python Renderer

Completed: `python-renderer/` and the `aimm_python` service have been removed.

### Add Monitoring

**Create** cron entry for cleanup:
```bash
# /etc/cron.d/aimm-pdf-cleanup
0 3 * * * www-data docker exec aimm_yii php yii pdf/cleanup >> /var/log/aimm/pdf-cleanup.log 2>&1
```

---

## File Reference

| Phase | File | Purpose |
|-------|------|---------|
| 1 | `docker/gotenberg/Dockerfile` | Gotenberg with curl |
| 1 | `docker-compose.yml` | Add gotenberg service |
| 1 | `src/dto/pdf/RenderBundle.php` | Immutable render bundle |
| 1 | `src/dto/pdf/RenderBundleBuilder.php` | Builder with validation |
| 1 | `src/dto/pdf/PdfOptions.php` | Gotenberg options |
| 1 | `src/clients/GotenbergClient.php` | HTTP client |
| 1 | `src/commands/PdfController.php` | Console commands |
| 2 | `web/scss/*.scss` | Report styling |
| 2 | `src/views/report/**/*.php` | Report templates |
| 2 | `src/controllers/ReportController.php` | Preview + API |
| 3 | `migrations/m260110_*_create_jobs_table.php` | Jobs persistence |
| 3 | `src/dto/pdf/*.php` | Report data DTOs |
| 3 | `src/queries/JobRepository.php` | Job CRUD |
| 3 | `src/handlers/pdf/*.php` | PDF generation logic |
| 3 | `src/adapters/LocalStorageAdapter.php` | File storage |
| 3 | `src/jobs/PdfGenerationJob.php` | Queue job (queue mode only) |
| 4 | `src/clients/AnalyticsClient.php` | Chart generation |
| 4 | `tests/unit/handlers/pdf/*.php` | Unit tests |
