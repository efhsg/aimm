# Phase 2 Analysis Design Document Prompt

Use this prompt with the source files to generate `docs/design/phase-2-analysis.md`.

---

## System Role

You are a Senior Software Architect specializing in financial data pipelines. You value strict typing, deterministic systems, and "data provenance" (knowing exactly where every number came from).

## Task

Write the technical design document for Phase 2: Analysis of the Aimm project.

**Output File:** `docs/design/phase-2-analysis.md`

## Context

The Aimm pipeline has three phases:

1. **Collection (Done):** Gathers raw data into dossier database tables.
2. **Analysis (Your Task):** Deterministic processing of dossier data to produce a ReportDTO.
3. **Rendering (Future):** "Dumb" Gotenberg HTML-to-PDF render step that turns ReportDTO into a PDF.

## Reference Material

- **Requirements:** Use the "Phase 2: Analyze" section of `docs/design/project-description.md` as the source of truth for logic (Gap Calculation, Rating Logic).
- **Style Guide:** Use `docs/design/phase-1-collection.md` as the strict template for structure, depth, and code examples.

---

## Specific Requirements for Phase 2 Design

### 1. Database-First Architecture

Phase 2 reads from dossier tables, NOT from datapack JSON files.

- **Input:** `peer_group_slug` (string) — identifies the peer group to analyze
- **Tables queried:** `industry_peer_group`, `industry_peer_group_member`, `valuation_snapshot`, `annual_financial`, `quarterly_financial`, `ttm_financial`, `macro_indicator`
- **Output:** `ReportDTO` (serialized to JSON for Phase 3)
- **Signature:** `(string $peerGroupSlug) -> ReportDTO`

### 2. Strict Determinism

Phase 2 performs **ZERO external network calls**. It is a pure function over database state. All calculations must be recomputable from the same database snapshot.

### 3. DTO Definitions

Define `app\dto\ReportDto` and its children. This DTO is the "ViewModel" for the PDF:

| Child DTO | Purpose |
|-----------|---------|
| `HeaderData` | Focal company name, report date, current price, market cap |
| `PeerComparison` | Table data comparing Focal vs each Peer (raw values) |
| `PeerAverages` | Computed averages for each metric (excluding focal, excluding nulls) |
| `ValuationGaps` | Individual gaps per metric with direction (undervalued/overvalued) |
| `CompositeGap` | Average of valid gaps (null if < 2 gaps available) |
| `FundamentalsAssessment` | Trend (Improving/Mixed/Deteriorating) + reasoning |
| `RiskAssessment` | Profile (Acceptable/Elevated/Unacceptable) + factors |
| `RatingResult` | Final BUY/HOLD/SELL + rule_path (e.g., `BUY_ALL_CONDITIONS`) |

### 4. Core Handlers to Detail

#### AnalyzeReportHandler
Orchestrator that coordinates all sub-handlers.

#### CalculateGapsHandler
Gap calculation with metric directionality:

```
For multiples (P/E, EV/EBITDA) — lower is better:
    gap = ((peer_avg - focal) / peer_avg) × 100
    Positive gap = focal is cheaper = undervalued

For yields (FCF yield, div yield) — higher is better:
    gap = ((focal - peer_avg) / peer_avg) × 100
    Positive gap = focal yields more = undervalued
```

#### DetermineRatingHandler
The specific IF/ELSE logic from project description:

```
IF fundamentals == Deteriorating:
    rating = SELL, rule_path = SELL_FUNDAMENTALS

ELSE IF risk == Unacceptable:
    rating = SELL, rule_path = SELL_RISK

ELSE IF valuation_gap == null:
    rating = HOLD, rule_path = HOLD_INSUFFICIENT_DATA

ELSE IF gap > 15% AND fundamentals == Improving AND risk == Acceptable:
    rating = BUY, rule_path = BUY_ALL_CONDITIONS

ELSE:
    rating = HOLD, rule_path = HOLD_DEFAULT
```

#### AssessFundamentalsHandler
Determines `Improving/Mixed/Deteriorating` from TTM financial trends (revenue, EBITDA, FCF growth).

#### AssessRiskHandler
Determines `Acceptable/Elevated/Unacceptable` from leverage ratios, debt metrics, etc.

### 5. Transformers to Detail

| Transformer | Purpose |
|-------------|---------|
| `PeerAverageTransformer` | Computes averages excluding focal company and null values |
| `CompositeGapTransformer` | Averages valid gaps; returns null if < 2 gaps |

### 6. Enums to Define

```php
enum Rating: string {
    case Buy = 'BUY';
    case Hold = 'HOLD';
    case Sell = 'SELL';
}

enum RatingRulePath: string {
    case BuyAllConditions = 'BUY_ALL_CONDITIONS';
    case HoldDefault = 'HOLD_DEFAULT';
    case HoldInsufficientData = 'HOLD_INSUFFICIENT_DATA';
    case SellFundamentals = 'SELL_FUNDAMENTALS';
    case SellRisk = 'SELL_RISK';
}

enum Fundamentals: string {
    case Improving = 'IMPROVING';
    case Mixed = 'MIXED';
    case Deteriorating = 'DETERIORATING';
}

enum Risk: string {
    case Acceptable = 'ACCEPTABLE';
    case Elevated = 'ELEVATED';
    case Unacceptable = 'UNACCEPTABLE';
}

enum GapDirection: string {
    case Undervalued = 'UNDERVALUED';
    case FairlyValued = 'FAIRLY_VALUED';
    case Overvalued = 'OVERVALUED';
}
```

### 7. Queries to Detail

| Query Class | Purpose |
|-------------|---------|
| `PeerGroupQuery` | Load peer group with members, identify focal |
| `ValuationSnapshotQuery` | Get latest valuations for ticker list |
| `TtmFinancialQuery` | Get TTM financials for trend analysis |
| `MacroIndicatorQuery` | Get latest macro data |

### 8. The Gate (AnalysisGateValidator)

Must verify:

1. **Schema compliance:** ReportDTO matches `report-dto.schema.json`
2. **Peer average recomputation:** Recalculate averages, must match reported
3. **Gap recomputation:** Recalculate each gap, must match reported
4. **Rating recomputation:** Re-apply rating logic, must match rating + rule_path
5. **Composite gap rule:** Verify null if < 2 gaps, else average matches
6. **Temporal sanity:** No past events marked as "upcoming" in catalysts

### 9. CLI Command

```bash
yii analyze/report global-energy-supermajors

# Output: runtime/reports/{peer_group_slug}/{uuid}/report-dto.json
```

---

## Source Files to Attach

When using this prompt, attach the following files:

1. **Source 1:** `docs/design/project-description.md` (Requirements)
2. **Source 2:** `docs/design/phase-1-collection.md` (Style/Structure Example)
