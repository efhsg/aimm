# PDF Generation Strategy: HTML/CSS + Headless Browser (Implementation-Ready)

**Date:** 2026-01-09  
**Status:** Definitive  
**Context:** Moving from Python/ReportLab stub to a robust HTML-to-PDF pipeline.

## 1. Executive Summary

This document defines the technical design for "institutional-grade" PDF reporting in AIMM. Based on the trade-off analysis and critical review, we adopt an **HTML/SCSS → Headless Browser (Chromium) → PDF** approach.

**Key Decision:** Replace the Python-based PDF layout renderer (ReportLab) with **Gotenberg** (Dockerized Headless Chrome).

**Core Principles:**
- **Determinism:** Rendering is driven by a self-contained `RenderBundle` (HTML + assets). **No external network calls** during render.
- **Visual Fidelity:** Pixel-perfect layout using SCSS compiled to CSS.
- **Observability:** Every render is traceable via `traceId` with per-step timings. **Debug artifacts are Dev-only**.
- **Resilience:** Sync-first generation when render time is ≤10s; queue-based processing is enabled when p95 exceeds 10s or bursts saturate PHP-FPM/Gotenberg, with bounded retries for transient failures.

---

## 2. Architecture

### 2.1 High-Level Flow (Sync-first, Queue-enabled When Needed)

1. **User Request:** User requests a report via Web UI or API.
2. **Validation & Job Record (Yii2 Web):**
   - Validates request (authorization is out of scope for initial implementation).
   - Computes `params_hash` from request options (recursive, stable key ordering).
   - Creates/gets a `jobs` record via idempotent insert:
     - On success: `status=queued`, set `trace_id`.
     - On unique conflict `(report_id, params_hash)`: return the existing `jobId`.
3. **Mode Decision:**
   - **Sync mode (`PDF_GENERATION_MODE=sync`)**: if expected end-to-end time is ≤10s, transition `queued → processing` and run inline.
   - **Queue mode (`PDF_GENERATION_MODE=queue`)**: push `jobId` to the queue and return immediately.
4. **Processing (Yii2 Worker or Inline):**
   - Acquire row lock (`SELECT ... FOR UPDATE`) inside a DB transaction.
   - Atomically transitions job `queued → processing` (guard against double-processing).
   - Fetches data from MySQL (financials, peer groups).
   - Calls analytics service to obtain chart **bytes** (PNG @ 2x/3x).
   - Renders Yii2 view templates into standalone HTML (`index.html`), plus `header.html` / `footer.html`.
   - Assembles `RenderBundle` (HTML + compiled CSS + fonts + images).
   - Sends bundle to Gotenberg `POST /forms/chromium/convert/html`.
5. **Rendering (Gotenberg):**
   - Chromium renders HTML using provided assets.
   - Prints to PDF using provided header/footer HTML.
   - Returns PDF bytes.
6. **Delivery:**
   - Worker stores PDF via `StorageInterface`, persists `output_uri` in `jobs`.
   - Updates job status to `completed` (or `failed` with error details).

### 2.2 Job Lifecycle & API

**Endpoints:**
- `POST /api/reports/generate` → `{"jobId":"..."}`
- `GET /api/jobs/{jobId}` → `{"status":"queued|processing|completed|failed","reportId":"...","outputUri":null|"...","error":null|{...}}`
- `GET /api/reports/{reportId}/download` → streams PDF

**Access Control:** Out of scope for initial implementation (add later).

**Transitions:** `queued → processing → completed` (or `failed`). In sync mode, `queued` is transient within the request.

**Jobs Table:** Always used (both sync and queue modes) for idempotency, auditing, and cacheability.

**Configuration:** set `pdfGenerationMode` and `pdfRenderBudgetMs` in `yii/config/params.php` (env-backed, defaults to `sync` and `10000`).

---

## 3. Persistence

### 3.1 `jobs` Table (MySQL)

**Table:** `jobs`
- `id` (PK, UUID)
- `report_id` (FK)
- `params_hash` (CHAR(64) hex SHA-256)
- `requester_id` (FK)
- `status` (ENUM: queued, processing, completed, failed)
- `trace_id` (CHAR(36) or UUID)
- `output_uri` (VARCHAR, nullable)  ← local path or s3://... or https://...
- `error_code` (VARCHAR, nullable)
- `error_message` (TEXT, nullable)
- `attempts` (INT, default 0)
- `created_at`, `updated_at`, `finished_at` (nullable)

**Idempotency:** Unique index on `(report_id, params_hash)`.
- Enqueue must be implemented as **insert-or-return-existing** (no race-prone “check then insert”).
- `params_hash` must be computed from recursively normalized options (stable key ordering).

---

## 4. Infrastructure (Docker)

### 4.1 Gotenberg Service

We replace the old `aimm_python` *PDF renderer* service with Gotenberg. If analytics is needed later, add a separate
`aimm_analytics` service.

**Important:** The stock `gotenberg/gotenberg` image may not include `curl/wget`. To make healthchecks reliable, we use a tiny derived image that includes `curl`.

**Dockerfile (recommended):**
```dockerfile
FROM gotenberg/gotenberg:8
USER root
RUN apt-get update \
    && apt-get install -y curl \
    && rm -rf /var/lib/apt/lists/*
USER gotenberg
```

**docker-compose.yml**
```yaml
services:
  gotenberg:
    container_name: aimm_gotenberg
    image: aimm/gotenberg:8
    restart: unless-stopped
    command:
      - "gotenberg"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3000/health"]
      interval: 10s
      timeout: 3s
      retries: 5
    environment:
      LOG_LEVEL: info
    networks:
      - default
```

### 4.2 Concurrency & Scaling

- **Generation mode:** `PDF_GENERATION_MODE=sync` by default; enable queue mode when p95 render time exceeds 10s or bursts saturate PHP-FPM/Gotenberg.
- **Queue (queue mode):** `yiisoft/yii2-queue` (Redis driver).
- **Retries (queue mode):** `Max Attempts = 3` (**1 initial + 2 retries**) with backoff `10s, 20s`.
- **Retryable failures:** network errors, timeouts, HTTP 5xx.
- **Non-retryable failures:** validation errors / HTTP 4xx.
- **Worker concurrency (queue mode):** bounded via `supervisord numprocs=N`.
  - Start with `N=2` and tune based on memory and p95 render time.
- **Circuit breaker:** if Gotenberg healthcheck fails, job transitions to `failed` with `error_code=GOTENBERG_UNHEALTHY` (non-retryable until fixed operationally).

---

## 5. Application Design (Yii2)

### 5.1 RenderBundle Contract

Rendering input is strictly defined and validated. Uses a factory-style fluent interface for consistent construction and immutable output.

```php
final readonly class RenderBundle
{
    /**
     * @param array<string, string|resource> $files relative path => bytes OR stream
     */
    private function __construct(
        public string $traceId,
        public string $indexHtml,
        public ?string $headerHtml,
        public ?string $footerHtml,
        public array $files,
        public int $totalBytes,
    ) {}

    public static function factory(string $traceId): RenderBundleFactory
    {
        return new RenderBundleFactory($traceId);
    }
}

final class RenderBundleFactory
{
    private string $indexHtml = '';
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;

    /** @var array<string, string|resource> */
    private array $files = [];
    private int $totalBytes = 0;

    private const SIZE_WARN_BYTES = 10 * 1024 * 1024;  // 10MB
    private const SIZE_FAIL_BYTES = 50 * 1024 * 1024;  // 50MB

    private const CSS_EXTENSIONS = ['css', 'scss'];

    public function __construct(
        private readonly string $traceId,
    ) {}

    public function withIndexHtml(string $html): self
    {
        $this->assertNoExternalRefs($html, 'index.html');
        $this->indexHtml = $html;
        return $this;
    }

    public function withHeaderHtml(?string $html): self
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html, 'header.html');
        }
        $this->headerHtml = $html;
        return $this;
    }

    public function withFooterHtml(?string $html): self
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html, 'footer.html');
        }
        $this->footerHtml = $html;
        return $this;
    }

    /**
     * @param string|resource $content
     */
    public function addFile(string $path, $content, ?int $byteSize = null): self
    {
        $this->validatePath($path);

        // Validate CSS/text files for external references
        if ($this->isTextAsset($path) && is_string($content)) {
            $this->assertNoExternalRefs($content, $path);
        }

        $this->files[$path] = $content;

        if ($byteSize !== null) {
            $this->totalBytes += $byteSize;
        }

        return $this;
    }

    public function build(): RenderBundle
    {
        if ($this->indexHtml === '') {
            throw new \InvalidArgumentException('indexHtml is required');
        }

        if ($this->totalBytes > self::SIZE_FAIL_BYTES) {
            throw new BundleSizeExceededException(
                "Bundle size {$this->totalBytes} exceeds limit " . self::SIZE_FAIL_BYTES
            );
        }

        if ($this->totalBytes > self::SIZE_WARN_BYTES) {
            \Yii::warning("RenderBundle size {$this->totalBytes} exceeds warning threshold");
        }

        return new RenderBundle(
            $this->traceId,
            $this->indexHtml,
            $this->headerHtml,
            $this->footerHtml,
            $this->files,
            $this->totalBytes,
        );
    }

    private function isTextAsset(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::CSS_EXTENSIONS, true);
    }

    private function validatePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/')) {
            throw new SecurityException('Absolute paths forbidden');
        }
        if (str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new SecurityException('Path traversal forbidden');
        }
        if (!preg_match('#^[a-zA-Z0-9._/-]+$#', $path)) {
            throw new SecurityException('Invalid path characters');
        }
    }

    private function assertNoExternalRefs(string $text, string $source): void
    {
        $patterns = [
            '#(src|href|srcset)\s*=\s*["\']\s*(https?:|//)#i',
            '#url\s*\(\s*["\']?\s*(https?:|//)#i',
            '#@import\s+["\']\s*(https?:|//)#i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text)) {
                throw new SecurityException(
                    "External resources forbidden in RenderBundle: {$source}"
                );
            }
        }
    }
}
```

**Usage:**

```php
$bundle = RenderBundle::factory($traceId)
    ->withIndexHtml($indexHtml)
    ->withHeaderHtml($headerHtml)
    ->withFooterHtml($footerHtml)
    ->addFile('assets/report.css', $cssContent, strlen($cssContent))
    ->addFile('assets/fonts/franklin.woff2', $fontBytes, strlen($fontBytes))
    ->addFile('charts/revenue.png', $chartPng, strlen($chartPng))
    ->build();
```

**Validation enforced:**
- Path traversal and absolute paths blocked
- External refs (`http://`, `https://`, `//`) blocked in HTML and CSS
- Bundle size: warn > 10MB, fail > 50MB

### 5.2 GotenbergClient

**Class:** `src/clients/GotenbergClient.php`

Responsibilities:
- Convert `RenderBundle` into `multipart/form-data` for Gotenberg.
- Send to `POST /forms/chromium/convert/html` with strict timeouts.
- Attach `X-Trace-Id: {traceId}` header.
- Map response errors into retryable vs non-retryable exceptions.
  - HTTP 4xx → non-retryable (include status + response body snippet in logs).
  - HTTP 5xx/timeouts/network → retryable.

**Timeouts (initial defaults):**
- Connect: `2.0s`
- Overall: `30.0s` (tune based on report complexity)

**File mapping (multipart):**
- `index.html` (required)
- `header.html` (optional)
- `footer.html` (optional)
- Other assets using their relative paths as filenames (`assets/report.css`, `assets/fonts/*.woff2`, `charts/*.png`, etc.)

### 5.3 Analytics Service Contract

- **Timeout:** 15s per call.
- **Retries:** 1 retry on timeout (only).
- **Caching:** Cache chart bytes by hash of chart input (Redis TTL 1h).
- **Return type:** bytes for each chart (PNG), plus metadata (width/height/DPI).

### 5.4 Header/Footer Strategy

- Sources: `views/report/partials/_header.php` and `_footer.php`.
- Injected via `header.html` / `footer.html` files in the Gotenberg request.
- Page numbering placeholders:
  - `<span class="pageNumber"></span>`
  - `<span class="totalPages"></span>`
- Constraints:
  - Keep header/footer HTML small and stable (avoid heavy images).
  - Define margins in print CSS / options so content never overlaps header/footer.

### 5.5 Worker Failure Handling

- **Prod:** log with `traceId`, store `error_code/error_message` in `jobs`. **No artifact dumping**.
- **Dev:** dump bundle to `runtime/debug/pdf_failure_{traceId}/` for inspection:
  - `manifest.json` (traceId, jobId, generation mode, totalBytes, file list with bytes + sha256, `timings_ms` summary, `pdf_bytes`, `memory_peak_kb`, versions: app git SHA, gotenberg image tag, PHP version)
  - `error.json` (exception class/message/stack, retryable flag, HTTP status/body snippet if available)
  - `pdf_options.json` (exact form fields)
  - `index.html`, `header.html`, `footer.html`, `assets/`, `charts/`

### 5.6 PDF Render Options

Gotenberg accepts render options via form fields. We define a `PdfOptions` DTO with sensible defaults.

```php
final readonly class PdfOptions
{
    public function __construct(
        public string $paperWidth = '210mm',      // A4 width
        public string $paperHeight = '297mm',     // A4 height
        public string $marginTop = '25mm',        // Space for header
        public string $marginBottom = '20mm',     // Space for footer
        public string $marginLeft = '15mm',
        public string $marginRight = '15mm',
        public float $scale = 1.0,                // 0.1 - 2.0
        public bool $landscape = false,
        public bool $printBackground = true,      // Include CSS backgrounds
        public string $preferCssPageSize = 'false', // Let Gotenberg control size
    ) {}

    /** @return array<string, string> Form fields for Gotenberg */
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
            'preferCssPageSize' => $this->preferCssPageSize,
        ];
    }
}
```

**Presets:**

| Preset | Use case | Orientation | Margins |
|--------|----------|-------------|---------|
| `standard` | Default company report | Portrait | 25/20/15/15mm |
| `detailed` | Multi-page financials | Portrait | 20/15/12/12mm |
| `landscape` | Wide comparison tables | Landscape | 15/15/20/20mm |

**Integration with GotenbergClient:**

```php
public function render(RenderBundle $bundle, PdfOptions $options): string
{
    $multipart = $this->buildMultipart($bundle);

    foreach ($options->toFormFields() as $name => $value) {
        $multipart[] = ['name' => $name, 'contents' => $value];
    }

    // ... send request
}
```

### 5.7 Report Data Flow

Data flows from persistence through transformation to templates in a strict pipeline.

**Flow:**
```
ReportQuery → ReportData (DTO) → ViewRenderer → RenderBundle
```

**Components:**

1. **ReportQuery** (`src/queries/ReportQuery.php`)
   - Fetches report metadata, company info, financials, peer group data.
   - Returns raw DB rows; no business logic.

2. **ReportDataFactory** (`src/factories/ReportDataFactory.php`)
   - Transforms query results into typed DTOs.
   - Calls Analytics Service for chart data.
   - Assembles `ReportData` DTO.

3. **ReportData** (`src/dto/ReportData.php`)
   - Immutable DTO containing all data needed for rendering.
   - Strongly typed: `CompanyDto`, `FinancialsDto`, `PeerGroupDto`, `ChartDto[]`.

4. **ViewRenderer** (`src/handlers/pdf/ViewRenderer.php`)
   - Receives `ReportData`, renders Yii2 views.
   - Produces HTML strings for index, header, footer.
   - Collects asset paths (CSS, fonts, chart PNGs).

**Example DTO structure:**

```php
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

final readonly class FinancialsDto
{
    public function __construct(
        /** @var FinancialYearDto[] */
        public array $years,
        public ?MetricDto $revenue,
        public ?MetricDto $ebitda,
        public ?MetricDto $netIncome,
        // ... other metrics
    ) {}
}

final readonly class ChartDto
{
    public function __construct(
        public string $id,
        public string $type,           // 'bar', 'line', 'pie'
        public string $pngBytes,       // Base64 or raw bytes
        public int $width,
        public int $height,
        public int $dpi,
    ) {}
}
```

**Worker orchestration:**

```php
final class PdfGenerationHandler
{
    public function handle(string $jobId): void
    {
        $job = $this->jobRepository->findAndLock($jobId);

        // 1. Fetch data
        $queryResult = $this->reportQuery->execute($job->reportId);

        // 2. Transform to DTO
        $reportData = $this->reportDataFactory->create($queryResult, $job->traceId);

        // 3. Render views to HTML
        $renderedViews = $this->viewRenderer->render($reportData);

        // 4. Assemble bundle
        $bundle = $this->bundleAssembler->assemble($renderedViews, $reportData);

        // 5. Generate PDF
        $pdfBytes = $this->gotenbergClient->render($bundle, $job->pdfOptions);

        // 6. Store and complete
        $uri = $this->storage->store($pdfBytes, $job->outputFilename());
        $this->jobRepository->complete($jobId, $uri);
    }
}
```

---

## 6. Templating Strategy (HTML/SCSS)

### 6.1 Directory Structure
```text
yii/src/views/report/
  layouts/pdf_main.php
  partials/_header.php
  partials/_footer.php
  partials/_financials.php
  partials/_charts.php
  index.php

yii/web/scss/
  report.scss
  _tokens.scss
  _tables.scss
  _print.scss

yii/web/css/
  report.css   # compiled artifact (tracked or built in CI)

yii/web/fonts/
  franklin/*.woff2
```

### 6.2 SCSS Build Pipeline

- `npm run build:css` compiles `web/scss/report.scss` → `web/css/report.css` using `sass`.
- The worker reads `web/css/report.css` and includes it in the bundle as `assets/report.css`.
- Determinism requires that the deployed `report.css` is immutable per release (commit hash or build artifact versioning).

### 6.3 Institutional Table Contract (minimum)

- `thead { display: table-header-group; }` must repeat on new pages.
- Text columns wrap; numeric columns are `nowrap`.
- Numeric alignment uses `tabular-nums` + right alignment.
- Fixed widths for standard columns (e.g., `Year = 15mm`) to prevent layout shifts.
- Avoid row splits using `break-inside: avoid; page-break-inside: avoid;`.

### 6.4 Charts

- PNG @ 2x/3x resolution.
- CSS controls the container size; image uses `width: 100%; height: auto;`.

---

## 7. Implementation Roadmap

### Phase 1: Infra + Hello World
1. Add `aimm/gotenberg:8` derived image + docker-compose service.
2. Implement `RenderBundle` + `GotenbergClient`.
3. Add a console command that sends a tiny bundle (HTML + CSS + one image) and stores output.
4. Implement generation handler + status transitions (`queued → processing → completed/failed`) usable inline; add queue worker skeleton only if queue mode is enabled.

### Phase 2: Templating Foundation
1. Add SCSS build script.
2. Implement base templates + header/footer.
3. Add dev preview route (`/report/preview`).

### Phase 3: Data Integration + Persistence
1. Implement `jobs` table + idempotent enqueue.
2. Implement `StorageInterface` (local disk MVP).
3. Add “torture fixtures” for tables and long text.

### Phase 4: Charts + Regression
1. Integrate analytics charts with caching.
2. Add golden master PDFs; pixel-diff via ImageMagick with `fuzz=5%` in a containerized, stable environment.

---

## 8. Retention & Cleanup Policy

### 8.1 Job Lifecycle Retention

| Job Status | Retention | Cleanup Action |
|------------|-----------|----------------|
| `completed` | 90 days after `finished_at` | Delete job record |
| `failed` | 30 days after `finished_at` | Delete job record |
| `queued` | 24 hours (stale) | Transition to `failed` with `STALE_JOB` |
| `processing` | 1 hour (stuck) | Transition to `failed` with `STUCK_JOB` |

### 8.2 PDF File Retention

| Storage Type | Retention | Notes |
|--------------|-----------|-------|
| Local disk | 30 days | Cleanup via cron |
| S3/Object storage | 90 days | Lifecycle policy |
| User-downloaded | Indefinite | Not our responsibility |

**Important:** PDFs are regenerable. Deletion is safe as long as the source data exists.

### 8.3 Cleanup Implementation

**Cron job:** Daily at 03:00 UTC.

```php
final class JobCleanupHandler
{
    private const COMPLETED_RETENTION_DAYS = 90;
    private const FAILED_RETENTION_DAYS = 30;
    private const STALE_QUEUED_HOURS = 24;
    private const STUCK_PROCESSING_HOURS = 1;

    public function handle(): CleanupResult
    {
        $result = new CleanupResult();

        // 1. Mark stale queued jobs as failed
        $result->staleJobsMarked = $this->markStaleJobs();

        // 2. Mark stuck processing jobs as failed
        $result->stuckJobsMarked = $this->markStuckJobs();

        // 3. Delete expired completed jobs + their PDFs
        $result->completedJobsDeleted = $this->deleteExpiredJobs(
            'completed',
            self::COMPLETED_RETENTION_DAYS
        );

        // 4. Delete expired failed jobs (no PDFs to delete)
        $result->failedJobsDeleted = $this->deleteExpiredJobs(
            'failed',
            self::FAILED_RETENTION_DAYS
        );

        return $result;
    }

    private function deleteExpiredJobs(string $status, int $retentionDays): int
    {
        $cutoff = new \DateTimeImmutable("-{$retentionDays} days");

        $jobs = $this->jobRepository->findExpired($status, $cutoff);

        foreach ($jobs as $job) {
            if ($job->outputUri !== null) {
                $this->storage->delete($job->outputUri);
            }
            $this->jobRepository->delete($job->id);
        }

        return count($jobs);
    }
}
```

### 8.4 Storage Interface

```php
interface StorageInterface
{
    /** Store PDF bytes, return URI (local path or s3://...) */
    public function store(string $bytes, string $filename): string;

    /** Delete by URI. Idempotent (no error if missing). */
    public function delete(string $uri): void;

    /** Stream PDF bytes for download. */
    public function stream(string $uri): StreamInterface;

    /** Check if file exists. */
    public function exists(string $uri): bool;
}
```

### 8.5 Monitoring

Track via metrics:
- `pdf_jobs_cleaned_total{status="completed|failed|stale|stuck"}` — counter
- `pdf_storage_bytes` — gauge (total storage used)
- `pdf_jobs_pending` — gauge (queued + processing count)

**Alerts:**
- `pdf_jobs_pending > 100` for 15 minutes → queue backup (queue mode only)
- `pdf_storage_bytes > 10GB` → storage pressure
- `pdf_jobs_cleaned_total{status="stuck"} > 0` → investigate worker health

---

## 9. Migration Checklist

- [ ] Docker: add Gotenberg service + healthcheck (via derived image).
- [ ] Queue (if p95 > 10s or bursty traffic): configure `yii2-queue` (Redis) + worker supervisor.
- [ ] Persistence: implement `jobs` table + idempotent enqueue.
- [ ] Contract: implement `RenderBundle` validation (HTML + CSS checks).
- [ ] Client: implement `GotenbergClient` (timeouts, trace header, error mapping).
- [ ] Analytics: implement chart bytes contract + caching.
- [ ] Templates: add report views + header/footer templates.
- [ ] SCSS: add build pipeline and ship compiled `report.css` as an immutable artifact.
- [ ] Storage: implement `StorageInterface` (local MVP), `output_uri` in jobs.
- [ ] Testing: torture fixtures + golden masters.
- [x] Cleanup: remove old `python-renderer` service and files.
