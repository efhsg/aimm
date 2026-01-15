# Implementation Prompt: PDF Ranking Report

## Context

You are implementing a feature for AIMM, a PHP 8.x / Yii 2 financial data system. The project uses strict coding standards documented in `.claude/rules/`.

## Goal

Make the PDF report show the same information as the web ranking page at `/admin/industry/{slug}/ranking`.

## Current State

The PDF generation system is already functional:
- `PdfGenerationHandler` orchestrates the pipeline
- `ReportDataFactory::create()` builds a single-company `ReportData` DTO
- `ViewRenderer::render()` renders `views/report/index.php`
- Gotenberg converts HTML to PDF

The problem: Current PDF shows ONE company's financial metrics. The ranking page shows ALL companies in a ranked table.

## Source Data

The report JSON (stored in `analysis_report.report_json`) contains:

```json
{
  "metadata": {
    "report_id": "uuid",
    "industry_name": "US Tech Giants",
    "company_count": 10,
    "generated_at": "2026-01-15T10:30:00Z",
    "data_as_of": "2026-01-14"
  },
  "company_analyses": [
    {
      "rank": 1,
      "ticker": "AAPL",
      "name": "Apple Inc",
      "rating": "buy",
      "fundamentals": {
        "assessment": "improving",
        "composite_score": 0.45
      },
      "risk": {
        "assessment": "acceptable"
      },
      "valuation_gap": {
        "composite_gap": 15.2,
        "direction": "undervalued"
      },
      "valuation": {
        "market_cap_billions": 3200.5
      }
    }
  ],
  "group_averages": {
    "fwd_pe": 25.3,
    "ev_ebitda": 18.7,
    "fcf_yield_percent": 3.2,
    "div_yield_percent": 0.8
  }
}
```

## Target Output

The PDF should have these sections (matching the web ranking page):

### 1. Report Details Card
- Companies Analyzed: `{company_count}`
- Generated At: `{generated_at}`
- Data As Of: `{data_as_of}`
- Report ID: `{report_id}`

### 2. Industry Averages Card
- Fwd P/E: `{fwd_pe}x`
- EV/EBITDA: `{ev_ebitda}x`
- FCF Yield: `{fcf_yield_percent}%`
- Div Yield: `{div_yield_percent}%`

### 3. Company Rankings Table

| Rank | Ticker | Company | Rating | Fundamentals | Risk | Valuation Gap | Market Cap |
|------|--------|---------|--------|--------------|------|---------------|------------|
| #1 | AAPL | Apple Inc | BUY (green badge) | Improving (green) 0.45 | Acceptable (green) | +15.2% (green) | $3,200.5B |

Badge colors:
- Rating: buy=green, hold=yellow, sell=red
- Fundamentals: improving=green, mixed=yellow, deteriorating=red
- Risk: acceptable=green, elevated=yellow, unacceptable=red
- Valuation Gap: undervalued=green, overvalued=red

### 4. Rating Summary Card
- BUY: 4 companies (AAPL, MSFT, GOOGL, META)
- HOLD: 3 companies (AMZN, NVDA, TSLA)
- SELL: 1 company (NFLX)

## Implementation Tasks

### Task 1: Create DTOs

Create in `yii/src/dto/pdf/`:

**RankingReportData.php**
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

use DateTimeImmutable;

final readonly class RankingReportData
{
    /**
     * @param CompanyRankingDto[] $companyRankings
     */
    public function __construct(
        public string $reportId,
        public string $traceId,
        public string $industryName,
        public RankingMetadataDto $metadata,
        public GroupAveragesDto $groupAverages,
        public array $companyRankings,
        public DateTimeImmutable $generatedAt,
    ) {}
}
```

**RankingMetadataDto.php**
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class RankingMetadataDto
{
    public function __construct(
        public int $companyCount,
        public string $generatedAt,
        public string $dataAsOf,
        public string $reportId,
    ) {}
}
```

**GroupAveragesDto.php**
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class GroupAveragesDto
{
    public function __construct(
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
    ) {}

    public function formatFwdPe(): string
    {
        return $this->fwdPe !== null ? number_format($this->fwdPe, 1) . 'x' : '-';
    }

    public function formatEvEbitda(): string
    {
        return $this->evEbitda !== null ? number_format($this->evEbitda, 1) . 'x' : '-';
    }

    public function formatFcfYield(): string
    {
        return $this->fcfYieldPercent !== null ? number_format($this->fcfYieldPercent, 1) . '%' : '-';
    }

    public function formatDivYield(): string
    {
        return $this->divYieldPercent !== null ? number_format($this->divYieldPercent, 2) . '%' : '-';
    }
}
```

**CompanyRankingDto.php**
```php
<?php

declare(strict_types=1);

namespace app\dto\pdf;

final readonly class CompanyRankingDto
{
    public function __construct(
        public int $rank,
        public string $ticker,
        public string $name,
        public string $rating,
        public string $fundamentalsAssessment,
        public float $fundamentalsScore,
        public string $riskAssessment,
        public ?float $valuationGapPercent,
        public ?string $valuationGapDirection,
        public ?float $marketCapBillions,
    ) {}

    public function getRatingBadgeClass(): string
    {
        return match ($this->rating) {
            'buy' => 'badge--success',
            'sell' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getFundamentalsBadgeClass(): string
    {
        return match ($this->fundamentalsAssessment) {
            'improving' => 'badge--success',
            'deteriorating' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getRiskBadgeClass(): string
    {
        return match ($this->riskAssessment) {
            'acceptable' => 'badge--success',
            'unacceptable' => 'badge--danger',
            default => 'badge--warning',
        };
    }

    public function getGapClass(): string
    {
        return match ($this->valuationGapDirection) {
            'undervalued' => 'text-success',
            'overvalued' => 'text-danger',
            default => '',
        };
    }

    public function formatValuationGap(): string
    {
        if ($this->valuationGapPercent === null) {
            return '-';
        }
        $sign = $this->valuationGapPercent > 0 ? '+' : '';
        return $sign . number_format($this->valuationGapPercent, 1) . '%';
    }

    public function formatMarketCap(): string
    {
        if ($this->marketCapBillions === null) {
            return '-';
        }
        return '$' . number_format($this->marketCapBillions, 1) . 'B';
    }
}
```

### Task 2: Update ReportDataFactory

File: `yii/src/factories/pdf/ReportDataFactory.php`

Add this method:

```php
public function createRanking(string $reportId, string $traceId): RankingReportData
{
    $row = $this->reportRepository->findByReportId($reportId);

    if ($row === null) {
        throw new RuntimeException("Report not found: {$reportId}");
    }

    $data = $this->reportRepository->decodeReport($row);

    return $this->buildRankingReportData($data, $traceId);
}

private function buildRankingReportData(array $data, string $traceId): RankingReportData
{
    $metadata = $data['metadata'] ?? [];
    $companyAnalyses = $data['company_analyses'] ?? [];
    $groupAverages = $data['group_averages'] ?? [];

    return new RankingReportData(
        reportId: $metadata['report_id'] ?? 'unknown',
        traceId: $traceId,
        industryName: $metadata['industry_name'] ?? 'Unknown Industry',
        metadata: new RankingMetadataDto(
            companyCount: $metadata['company_count'] ?? 0,
            generatedAt: $metadata['generated_at'] ?? '',
            dataAsOf: $metadata['data_as_of'] ?? '',
            reportId: $metadata['report_id'] ?? '',
        ),
        groupAverages: new GroupAveragesDto(
            fwdPe: $groupAverages['fwd_pe'] ?? null,
            evEbitda: $groupAverages['ev_ebitda'] ?? null,
            fcfYieldPercent: $groupAverages['fcf_yield_percent'] ?? null,
            divYieldPercent: $groupAverages['div_yield_percent'] ?? null,
        ),
        companyRankings: array_map(
            fn(array $analysis) => $this->buildCompanyRankingDto($analysis),
            $companyAnalyses
        ),
        generatedAt: new DateTimeImmutable(),
    );
}

private function buildCompanyRankingDto(array $analysis): CompanyRankingDto
{
    return new CompanyRankingDto(
        rank: $analysis['rank'] ?? 0,
        ticker: $analysis['ticker'] ?? '',
        name: $analysis['name'] ?? '',
        rating: $analysis['rating'] ?? 'hold',
        fundamentalsAssessment: $analysis['fundamentals']['assessment'] ?? 'mixed',
        fundamentalsScore: $analysis['fundamentals']['composite_score'] ?? 0.0,
        riskAssessment: $analysis['risk']['assessment'] ?? 'elevated',
        valuationGapPercent: $analysis['valuation_gap']['composite_gap'] ?? null,
        valuationGapDirection: $analysis['valuation_gap']['direction'] ?? null,
        marketCapBillions: $analysis['valuation']['market_cap_billions'] ?? null,
    );
}
```

Add imports at top:
```php
use app\dto\pdf\CompanyRankingDto;
use app\dto\pdf\GroupAveragesDto;
use app\dto\pdf\RankingMetadataDto;
use app\dto\pdf\RankingReportData;
```

### Task 3: Create View Templates

Create in `yii/src/views/report/`:

**ranking.php**
```php
<?php

declare(strict_types=1);

use app\dto\pdf\RankingReportData;

/**
 * @var yii\web\View $this
 * @var RankingReportData $reportData
 */

$logoPath = Yii::getAlias('@webroot/images/logo.svg');
if (!file_exists($logoPath)) {
    $logoPath = Yii::getAlias('@webroot/images/logo.png');
}

$logoBase64 = '';
if (file_exists($logoPath)) {
    $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
    $mimeType = $extension === 'svg' ? 'image/svg+xml' : 'image/' . $extension;
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoData);
}
?>
<div class="report">
    <header class="report__header">
        <div class="report__branding">
            <?php if ($logoBase64): ?>
                <img src="<?= $logoBase64 ?>" alt="AIMM Logo" class="report__logo">
            <?php endif; ?>
        </div>
        <h1 class="report__title">Rankings - <?= htmlspecialchars($reportData->industryName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="report__subtitle">
            Analysis as of <?= $reportData->generatedAt->format('F j, Y') ?>
        </p>
    </header>

    <?= $this->render('partials/_report_details', ['metadata' => $reportData->metadata]) ?>

    <?= $this->render('partials/_industry_averages', ['averages' => $reportData->groupAverages]) ?>

    <?= $this->render('partials/_ranking_table', ['rankings' => $reportData->companyRankings]) ?>

    <?= $this->render('partials/_rating_summary', ['rankings' => $reportData->companyRankings]) ?>
</div>
```

**partials/_report_details.php**
```php
<?php

declare(strict_types=1);

use app\dto\pdf\RankingMetadataDto;

/**
 * @var RankingMetadataDto $metadata
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Report Details</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Companies Analyzed</span>
            <span class="detail-value"><?= $metadata->companyCount ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Generated At</span>
            <span class="detail-value"><?= htmlspecialchars($metadata->generatedAt, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Data As Of</span>
            <span class="detail-value"><?= htmlspecialchars($metadata->dataAsOf, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Report ID</span>
            <span class="detail-value detail-value--mono"><?= htmlspecialchars($metadata->reportId, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</section>
```

**partials/_industry_averages.php**
```php
<?php

declare(strict_types=1);

use app\dto\pdf\GroupAveragesDto;

/**
 * @var GroupAveragesDto $averages
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Industry Averages</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Fwd P/E</span>
            <span class="detail-value"><?= $averages->formatFwdPe() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">EV/EBITDA</span>
            <span class="detail-value"><?= $averages->formatEvEbitda() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">FCF Yield</span>
            <span class="detail-value"><?= $averages->formatFcfYield() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Div Yield</span>
            <span class="detail-value"><?= $averages->formatDivYield() ?></span>
        </div>
    </div>
</section>
```

**partials/_ranking_table.php**
```php
<?php

declare(strict_types=1);

use app\dto\pdf\CompanyRankingDto;

/**
 * @var CompanyRankingDto[] $rankings
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Company Rankings</h2>
    <?php if (empty($rankings)): ?>
        <p class="text-muted">No companies analyzed.</p>
    <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Ticker</th>
                    <th>Company</th>
                    <th>Rating</th>
                    <th>Fundamentals</th>
                    <th>Risk</th>
                    <th>Valuation Gap</th>
                    <th>Market Cap</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rankings as $company): ?>
                    <tr>
                        <td class="col-numeric"><strong>#<?= $company->rank ?></strong></td>
                        <td class="col-mono"><strong><?= htmlspecialchars($company->ticker, ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($company->name, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $company->getRatingBadgeClass() ?>">
                                <?= strtoupper($company->rating) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $company->getFundamentalsBadgeClass() ?>">
                                <?= ucfirst($company->fundamentalsAssessment) ?>
                            </span>
                            <small class="text-muted">(<?= number_format($company->fundamentalsScore, 2) ?>)</small>
                        </td>
                        <td>
                            <span class="badge <?= $company->getRiskBadgeClass() ?>">
                                <?= ucfirst($company->riskAssessment) ?>
                            </span>
                        </td>
                        <td class="col-numeric <?= $company->getGapClass() ?>">
                            <?= $company->formatValuationGap() ?>
                        </td>
                        <td class="col-numeric">
                            <?= $company->formatMarketCap() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
```

**partials/_rating_summary.php**
```php
<?php

declare(strict_types=1);

use app\dto\pdf\CompanyRankingDto;

/**
 * @var CompanyRankingDto[] $rankings
 */

$buyCompanies = array_filter($rankings, fn($c) => $c->rating === 'buy');
$holdCompanies = array_filter($rankings, fn($c) => $c->rating === 'hold');
$sellCompanies = array_filter($rankings, fn($c) => $c->rating === 'sell');

$buyTickers = implode(', ', array_map(fn($c) => $c->ticker, $buyCompanies));
$holdTickers = implode(', ', array_map(fn($c) => $c->ticker, $holdCompanies));
$sellTickers = implode(', ', array_map(fn($c) => $c->ticker, $sellCompanies));
?>
<section class="report__section">
    <h2 class="report__section-title">Rating Summary</h2>
    <div class="summary-grid">
        <div class="summary-row">
            <span class="badge badge--success">BUY</span>
            <span class="summary-count"><?= count($buyCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $buyTickers ?: '-' ?></span>
        </div>
        <div class="summary-row">
            <span class="badge badge--warning">HOLD</span>
            <span class="summary-count"><?= count($holdCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $holdTickers ?: '-' ?></span>
        </div>
        <div class="summary-row">
            <span class="badge badge--danger">SELL</span>
            <span class="summary-count"><?= count($sellCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $sellTickers ?: '-' ?></span>
        </div>
    </div>
</section>
```

### Task 4: Update CSS

File: `yii/web/scss/report.scss`

Add these styles:

```scss
// Badges
.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 9pt;
  font-weight: 600;
  text-transform: uppercase;

  &--success {
    background: #d4edda;
    color: #155724;
  }

  &--warning {
    background: #fff3cd;
    color: #856404;
  }

  &--danger {
    background: #f8d7da;
    color: #721c24;
  }
}

// Text colors
.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
.text-muted { color: #6c757d; }

// Detail grid
.detail-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: tokens.$spacing-md;
  margin-bottom: tokens.$spacing-lg;
}

.detail-item {
  display: flex;
  flex-direction: column;
}

.detail-label {
  font-size: 9pt;
  color: tokens.$color-text-muted;
  margin-bottom: tokens.$spacing-xs;
}

.detail-value {
  font-size: 11pt;
  font-weight: 600;

  &--mono {
    font-family: tokens.$font-mono;
    font-size: 9pt;
  }
}

// Summary grid
.summary-grid {
  display: flex;
  flex-direction: column;
  gap: tokens.$spacing-md;
}

.summary-row {
  display: grid;
  grid-template-columns: 80px 120px 1fr;
  align-items: center;
  gap: tokens.$spacing-md;
}

.summary-count {
  font-weight: 600;
}

.summary-tickers {
  color: tokens.$color-text-muted;
  font-family: tokens.$font-mono;
  font-size: 9pt;
}

// Table enhancements
.col-mono {
  font-family: tokens.$font-mono;
}
```

### Task 5: Update ViewRenderer

File: `yii/src/handlers/pdf/ViewRenderer.php`

Add this method:

```php
public function renderRanking(RankingReportData $data): RenderedViews
{
    $indexHtml = $this->view->renderFile(
        $this->viewPath . '/ranking.php',
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
```

Add import:
```php
use app\dto\pdf\RankingReportData;
```

### Task 6: Update PdfGenerationHandler

File: `yii/src/handlers/pdf/PdfGenerationHandler.php`

In the `process()` method, replace:

```php
// OLD:
$reportData = $this->reportDataFactory->create($job['report_id'], $traceId);
$renderedViews = $this->viewRenderer->render($reportData);

// NEW:
$reportData = $this->reportDataFactory->createRanking($job['report_id'], $traceId);
$renderedViews = $this->viewRenderer->renderRanking($reportData);
```

### Task 7: Build CSS

```bash
cd yii && npm run build:css
```

## Verification

1. Navigate to ranking page in browser
2. Click "View PDF" button
3. Verify PDF contains all 4 sections
4. Compare values with web page
5. Check badge colors are correct

## Project Conventions

- Use `declare(strict_types=1);` in all PHP files
- Use `final readonly class` for DTOs
- Use `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` for HTML escaping
- Follow existing code patterns in `yii/src/dto/pdf/` and `yii/src/views/report/`
- Run linter after changes: `docker exec aimm_yii vendor/bin/php-cs-fixer fix`
