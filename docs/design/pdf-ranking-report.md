# PDF Ranking Report Implementation

**Date:** 2026-01-15
**Status:** Ready for Implementation

## Goal

Make the PDF show the same information as the ranking page (`/admin/industry/{slug}/ranking`).

## Current vs Target

| Section | Current PDF | Target PDF |
|---------|-------------|------------|
| Header | Single company name/ticker | Industry name, report date |
| Report Details | - | Company count, generated at, data as of, report ID |
| Industry Averages | - | Fwd P/E, EV/EBITDA, FCF Yield, Div Yield |
| Rankings Table | Single company metrics | All companies: Rank, Ticker, Company, Rating, Fundamentals, Risk, Valuation Gap, Market Cap |
| Rating Summary | - | BUY/HOLD/SELL counts with tickers |

## Data Source

The report JSON (`analysis_report.report_json`) already contains all required data:
- `metadata`: report_id, industry_name, company_count, generated_at, data_as_of
- `company_analyses[]`: rank, ticker, name, rating, fundamentals, risk, valuation_gap, valuation
- `group_averages`: fwd_pe, ev_ebitda, fcf_yield_percent, div_yield_percent

## Implementation Steps

### 1. Create Ranking DTOs

**Location:** `yii/src/dto/pdf/`

Create these new DTOs:

**`RankingReportData.php`** - Main DTO for ranking PDF
```
- reportId: string
- traceId: string
- industryName: string
- metadata: RankingMetadataDto
- groupAverages: GroupAveragesDto
- companyRankings: CompanyRankingDto[]
- generatedAt: DateTimeImmutable
```

**`RankingMetadataDto.php`**
```
- companyCount: int
- generatedAt: string
- dataAsOf: string
- reportId: string
```

**`GroupAveragesDto.php`**
```
- fwdPe: ?float
- evEbitda: ?float
- fcfYieldPercent: ?float
- divYieldPercent: ?float
```

**`CompanyRankingDto.php`**
```
- rank: int
- ticker: string
- name: string
- rating: string (buy|hold|sell)
- fundamentalsAssessment: string
- fundamentalsScore: float
- riskAssessment: string
- valuationGapPercent: ?float
- valuationGapDirection: ?string
- marketCapBillions: ?float
```

### 2. Update ReportDataFactory

**File:** `yii/src/factories/pdf/ReportDataFactory.php`

Add method `createRanking(string $reportId, string $traceId): RankingReportData`

This method:
1. Fetches report via `AnalysisReportReader::findByReportId()`
2. Decodes JSON
3. Maps `metadata` → `RankingMetadataDto`
4. Maps `group_averages` → `GroupAveragesDto`
5. Maps each `company_analyses[]` item → `CompanyRankingDto`
6. Returns `RankingReportData`

### 3. Create Ranking View Templates

**Location:** `yii/src/views/report/`

**`ranking.php`** - Main ranking report template
- Industry header with logo
- Includes all partials below

**`partials/_report_details.php`**
- Card with: Companies Analyzed, Generated At, Data As Of, Report ID

**`partials/_industry_averages.php`**
- Card with: Fwd P/E, EV/EBITDA, FCF Yield, Div Yield

**`partials/_ranking_table.php`**
- Table with columns: Rank, Ticker, Company, Rating, Fundamentals, Risk, Valuation Gap, Market Cap
- Badge styling for Rating (green=BUY, yellow=HOLD, red=SELL)
- Badge styling for Fundamentals (improving/mixed/deteriorating)
- Badge styling for Risk (acceptable/elevated/unacceptable)
- Color for Valuation Gap (green=undervalued, red=overvalued)

**`partials/_rating_summary.php`**
- Summary grid: BUY/HOLD/SELL counts with ticker lists

### 4. Add CSS for Badges

**File:** `yii/web/scss/report.scss`

Add badge styles:
```scss
.badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 9pt;
  font-weight: 600;

  &--success { background: #d4edda; color: #155724; }
  &--warning { background: #fff3cd; color: #856404; }
  &--danger { background: #f8d7da; color: #721c24; }
}

.text-success { color: #28a745; }
.text-danger { color: #dc3545; }
```

### 5. Update ViewRenderer

**File:** `yii/src/handlers/pdf/ViewRenderer.php`

Add method `renderRanking(RankingReportData $data): RenderedViews`

This method renders `views/report/ranking.php` instead of `views/report/index.php`.

### 6. Update PdfGenerationHandler

**File:** `yii/src/handlers/pdf/PdfGenerationHandler.php`

In `process()` method:
- Use `ReportDataFactory::createRanking()` instead of `create()`
- Use `ViewRenderer::renderRanking()` instead of `render()`

### 7. Rebuild CSS

```bash
cd yii && npm run build:css
```

## File Summary

| Action | File |
|--------|------|
| Create | `dto/pdf/RankingReportData.php` |
| Create | `dto/pdf/RankingMetadataDto.php` |
| Create | `dto/pdf/GroupAveragesDto.php` |
| Create | `dto/pdf/CompanyRankingDto.php` |
| Modify | `factories/pdf/ReportDataFactory.php` |
| Create | `views/report/ranking.php` |
| Create | `views/report/partials/_report_details.php` |
| Create | `views/report/partials/_industry_averages.php` |
| Create | `views/report/partials/_ranking_table.php` |
| Create | `views/report/partials/_rating_summary.php` |
| Modify | `web/scss/report.scss` |
| Modify | `handlers/pdf/ViewRenderer.php` |
| Modify | `handlers/pdf/PdfGenerationHandler.php` |

## Verification

1. Generate PDF from ranking page
2. Compare visually with web ranking page
3. Verify all sections present: Report Details, Industry Averages, Rankings Table, Rating Summary
4. Verify badge colors match web page
5. Verify data values match
