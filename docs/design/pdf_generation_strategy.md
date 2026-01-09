# PDF Generation Strategy: HTML/CSS + Headless Browser (Implementation-Ready)

**Date:** 2026-01-09  
**Status:** Design  
**Context:** Moving from Python/ReportLab stub to a robust HTML-to-PDF pipeline.

## 1. Executive Summary

This document defines the technical design for "institutional-grade" PDF reporting in AIMM. Based on the trade-off analysis and critical review, we adopt an **HTML/SCSS → Headless Browser (Chromium) → PDF** approach.

**Key Decision:** Replace the Python-based PDF layout renderer (ReportLab) with **Gotenberg** (Dockerized Headless Chrome).

**Core Principles:**
- **Determinism:** Rendering is driven by a self-contained `RenderBundle` (HTML + assets). **No external network calls** during render.
- **Visual Fidelity:** Pixel-perfect layout using SCSS compiled to CSS.
- **Observability:** Every render is traceable via `traceId`. **Debug artifacts are Dev-only**.
- **Resilience:** Queue-based generation to prevent concurrency saturation; bounded retries for transient failures.

---

## 2. Architecture

### 2.1 High-Level Flow (Lightweight Enqueue)

1. **User Request:** User requests a report via Web UI or API.
2. **Validation & Enqueue (Yii2 Web):**
   - Validates request and access to `reportId`.
   - Computes `params_hash` from request options (stable ordering).
   - Creates/gets a `jobs` record via idempotent insert:
     - On success: `status=queued`, set `trace_id`.
     - On unique conflict `(report_id, params_hash)`: return the existing `jobId`.
   - Pushes `jobId` to Redis queue.
   - Returns `jobId`.
3. **Processing (Yii2 Worker):**
   - Atomically transitions job `queued → processing` (guard against double-processing).
   - Fetches data from MySQL (financials, peer groups).
   - Calls Analytics Service (Python) to obtain chart **bytes** (PNG @ 2x/3x).
   - Renders Yii2 view templates into standalone HTML (`index.html`), plus `header.html` / `footer.html`.
   - Assembles `RenderBundle` (HTML + compiled CSS + fonts + images).
   - Sends bundle to Gotenberg `POST /forms/chromium/convert/html`.
4. **Rendering (Gotenberg):**
   - Chromium renders HTML using provided assets.
   - Prints to PDF using provided header/footer HTML.
   - Returns PDF bytes.
5. **Delivery:**
   - Worker stores PDF via `StorageInterface`, persists `output_uri` in `jobs`.
   - Updates job status to `completed` (or `failed` with error details).

### 2.2 Job Lifecycle & API

**Endpoints:**
- `POST /api/reports/generate` → `{"jobId":"..."}`
- `GET /api/jobs/{jobId}` → `{"status":"queued|processing|completed|failed","reportId":"...","outputUri":null|"...","error":null|{...}}`
- `GET /api/reports/{reportId}/download` → streams PDF

**Access Control:** `requester_id === user.id` OR `user.role === 'admin'`.

**Transitions:** `queued → processing → completed` (or `failed`).

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

---

## 4. Infrastructure (Docker)

### 4.1 Gotenberg Service

We replace the old `aimm_python` *PDF renderer* service with Gotenberg. (Python may remain as `aimm_analytics`.)

**Important:** The stock `gotenberg/gotenberg` image may not include `curl/wget`. To make healthchecks reliable, we use a tiny derived image that includes `curl`.

**Dockerfile (recommended):**
```dockerfile
FROM gotenberg/gotenberg:8
USER root
RUN apk add --no-cache curl || true
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
      - "--api-retry-count=3"
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

- **Queue:** `yiisoft/yii2-queue` (Redis driver).
- **Retries:** `Max Attempts = 3` (**1 initial + 2 retries**) with backoff `10s, 20s`.
- **Retryable failures:** network errors, timeouts, HTTP 5xx.
- **Non-retryable failures:** validation errors / HTTP 4xx.
- **Worker concurrency:** bounded via `supervisord numprocs=N`.
  - Start with `N=2` and tune based on memory and p95 render time.
- **Circuit breaker:** if Gotenberg healthcheck fails, job transitions to `failed` with `error_code=GOTENBERG_UNHEALTHY` (non-retryable until fixed operationally).

---

## 5. Application Design (Yii2)

### 5.1 RenderBundle Contract

Rendering input is strictly defined and validated. Large assets may be streamed.

```php
final class RenderBundle
{
    public string $traceId;

    public string $indexHtml;
    public ?string $headerHtml = null;
    public ?string $footerHtml = null;

    /** @var array<string, string|resource> relative path => bytes OR stream resource */
    public array $files = [];

    /** Total bundle size in bytes (estimated). */
    public int $totalBytes = 0;

    public function setIndexHtml(string $html): void
    {
        $this->assertNoExternalRefs($html);
        $this->indexHtml = $html;
    }

    public function setHeaderHtml(?string $html): void
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html);
        }
        $this->headerHtml = $html;
    }

    public function setFooterHtml(?string $html): void
    {
        if ($html !== null) {
            $this->assertNoExternalRefs($html);
        }
        $this->footerHtml = $html;
    }

    public function addFile(string $path, $content, ?int $byteSize = null): void
    {
        $this->validatePath($path);
        $this->files[$path] = $content;

        if ($byteSize !== null) {
            $this->totalBytes += $byteSize;
        }
    }

    private function validatePath(string $path): void
    {
        // Must be relative, allow subdirs, forbid traversal.
        if ($path === '' || str_starts_with($path, '/')) {
            throw new SecurityException('Absolute paths forbidden');
        }
        if (str_contains($path, '../') || str_contains($path, '..\\')) {
            throw new SecurityException('Path traversal forbidden');
        }
        // Allowed: letters, digits, dot, underscore, dash, slash
        if (!preg_match('#^[a-zA-Z0-9._/-]+$#', $path)) {
            throw new SecurityException('Invalid path characters');
        }
    }

    private function assertNoExternalRefs(string $text): void
    {
        // Reject http(s), protocol-relative, and other external refs in HTML/CSS text.
        // Apply to HTML strings AND to any text/css content we add.
        $patterns = [
            '#(src|href|srcset)\s*=\s*["\']\s*(https?:|//)#i',
            '#url\s*\(\s*["\']?\s*(https?:|//)#i',
            '#@import\s+["\']\s*(https?:|//)#i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text)) {
                throw new SecurityException('External resources forbidden in RenderBundle');
            }
        }
    }
}
```

**Bundle size budgets:**
- Warn when `totalBytes > 10MB`
- Hard fail when `totalBytes > 50MB`

**Enforcement note:** `assertNoExternalRefs()` must also run against **CSS text** (e.g., compiled `report.css`) before sending to Gotenberg.

### 5.2 GotenbergClient

**Class:** `src/clients/GotenbergClient.php`

Responsibilities:
- Convert `RenderBundle` into `multipart/form-data` for Gotenberg.
- Send to `POST /forms/chromium/convert/html` with strict timeouts.
- Attach `X-Trace-Id: {traceId}` header.
- Map response errors into retryable vs non-retryable exceptions.

**Timeouts (initial defaults):**
- Connect: `2.0s`
- Overall: `30.0s` (tune based on report complexity)

**File mapping (multipart):**
- `index.html` (required)
- `header.html` (optional)
- `footer.html` (optional)
- Other assets using their relative paths as filenames (`assets/report.css`, `assets/fonts/*.woff2`, `charts/*.png`, etc.)

### 5.3 Python Analytics Service Contract

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
- **Dev:** dump bundle to `runtime/debug/pdf_failure_{traceId}/` for inspection (includes a manifest with sizes and filenames).

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
4. Add worker job skeleton + status transitions (`queued → processing → completed/failed`).

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

## 8. Migration Checklist

- [ ] Docker: add Gotenberg service + healthcheck (via derived image).
- [ ] Queue: configure `yii2-queue` (Redis) + worker supervisor.
- [ ] Persistence: implement `jobs` table + idempotent enqueue.
- [ ] Contract: implement `RenderBundle` validation (HTML + CSS checks).
- [ ] Client: implement `GotenbergClient` (timeouts, trace header, error mapping).
- [ ] Analytics: implement chart bytes contract + caching.
- [ ] Templates: add report views + header/footer templates.
- [ ] SCSS: add build pipeline and ship compiled `report.css` as an immutable artifact.
- [ ] Storage: implement `StorageInterface` (local MVP), `output_uri` in jobs.
- [ ] Testing: torture fixtures + golden masters.
- [ ] Cleanup: remove old `python-renderer` service and files.
