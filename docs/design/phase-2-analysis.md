# Phase 2: Analysis — Technical Design Document

**Project:** Aimm (Equity Intelligence Pipeline)
**Phase:** 2 — Analysis
**Version:** 1.0
**Status:** Design Complete

---

## Table of Contents

1. [Overview](#1-overview)
2. [Enum Layer](#2-enum-layer)
3. [DTO Layer — Analysis Configuration](#3-dto-layer--analysis-configuration)
4. [DTO Layer — Report Output](#4-dto-layer--report-output)
5. [Transformers](#5-transformers)
6. [Handlers](#6-handlers)
7. [The Analysis Gate](#7-the-analysis-gate)
8. [CLI Interface](#8-cli-interface)
9. [Algorithm Reference](#9-algorithm-reference)
10. [Error Handling Strategy](#10-error-handling-strategy)
11. [Testing Strategy](#11-testing-strategy)
12. [Implementation Plan](#12-implementation-plan)

---

## 1. Overview

Phase 2 consumes the `IndustryDataPack` from Phase 1 (Collection) and produces a `ReportDTO` for Phase 3 (Rendering). The analysis is **purely deterministic** — no external API calls, no network requests. All calculations are recomputable from the DataPack.

### Core Principle

> **Deterministic analysis with configurable thresholds.** Every rating decision follows an auditable rule path. Thresholds are configurable per collection policy, enabling sector-specific analysis without code changes.

### Input/Output

| Item | Description |
|------|-------------|
| **Input** | `IndustryDataPack` from Phase 1 (via database or file) |
| **Output** | `ReportDTO` containing rating, fundamentals, risk, and peer comparison |
| **Gate** | `AnalysisGateValidator` — validates input completeness before analysis |

### Key Constraints

- **No external API calls** — all data comes from IndustryDataPack
- **No caching** — results are always recomputed
- **Configurable thresholds** — stored in `collection_policy` table
- **Auditable decisions** — every rating includes its rule path

### Technology Stack

- PHP 8.2+, Yii 2 Framework
- No additional dependencies beyond Phase 1

---

## 2. Enum Layer

### 2.1 Rating

Investment recommendation.

**Namespace:** `app\enums`

```php
namespace app\enums;

enum Rating: string
{
    case Buy = 'buy';
    case Hold = 'hold';
    case Sell = 'sell';
}
```

**Usage:**
- Final investment recommendation in `FocalAnalysis`
- Determined by `DetermineRatingHandler` based on fundamentals, risk, and valuation gap

### 2.2 RatingRulePath

Audit trail for rating decisions.

**Namespace:** `app\enums`

```php
namespace app\enums;

enum RatingRulePath: string
{
    // Sell paths
    case SellFundamentals = 'SELL_FUNDAMENTALS';
    case SellRisk = 'SELL_RISK';

    // Hold paths
    case HoldInsufficientData = 'HOLD_INSUFFICIENT_DATA';
    case HoldDefault = 'HOLD_DEFAULT';

    // Buy paths
    case BuyAllConditions = 'BUY_ALL_CONDITIONS';
}
```

**Decision Tree:**

```
IF fundamentals == Deteriorating:
    SELL (SELL_FUNDAMENTALS)
ELSE IF risk == Unacceptable:
    SELL (SELL_RISK)
ELSE IF composite_gap is null:
    HOLD (HOLD_INSUFFICIENT_DATA)
ELSE IF composite_gap > buyGapThreshold AND fundamentals == Improving AND risk == Acceptable:
    BUY (BUY_ALL_CONDITIONS)
ELSE:
    HOLD (HOLD_DEFAULT)
```

### 2.3 Fundamentals

Business fundamentals assessment.

**Namespace:** `app\enums`

```php
namespace app\enums;

enum Fundamentals: string
{
    case Improving = 'improving';
    case Mixed = 'mixed';
    case Deteriorating = 'deteriorating';
}
```

**Determination:**
- Based on multi-metric composite score (see §9.1)
- Score >= 0.3 → Improving
- Score <= -0.3 → Deteriorating
- Otherwise → Mixed

### 2.4 Risk

Financial risk assessment.

**Namespace:** `app\enums`

```php
namespace app\enums;

enum Risk: string
{
    case Acceptable = 'acceptable';
    case Elevated = 'elevated';
    case Unacceptable = 'unacceptable';
}
```

**Determination:**
- Based on multi-factor scoring (see §9.2)
- Any single factor at "unacceptable" level → overall Unacceptable
- Otherwise weighted average determines tier

### 2.5 GapDirection

Valuation gap interpretation.

**Namespace:** `app\enums`

```php
namespace app\enums;

enum GapDirection: string
{
    case Undervalued = 'undervalued';
    case Fair = 'fair';
    case Overvalued = 'overvalued';
}
```

**Determination:**
- Gap > fairValueThreshold → Undervalued
- Gap < -fairValueThreshold → Overvalued
- Otherwise → Fair

---

## 3. DTO Layer — Analysis Configuration

Configuration DTOs hold thresholds loaded from `collection_policy`.

### 3.1 AnalysisThresholds

Master threshold configuration for analysis.

**Namespace:** `app\dto\analysis`

```php
namespace app\dto\analysis;

final readonly class AnalysisThresholds
{
    public function __construct(
        public float $buyGapThreshold = 15.0,
        public float $fairValueThreshold = 5.0,
        public int $minMetricsForGap = 2,
        public FundamentalsWeights $fundamentalsWeights = new FundamentalsWeights(),
        public RiskThresholds $riskThresholds = new RiskThresholds(),
    ) {}

    public static function fromPolicy(array $policyJson): self
    {
        return new self(
            buyGapThreshold: $policyJson['buy_gap_threshold'] ?? 15.0,
            fairValueThreshold: $policyJson['fair_value_threshold'] ?? 5.0,
            minMetricsForGap: $policyJson['min_metrics_for_gap'] ?? 2,
            fundamentalsWeights: FundamentalsWeights::fromArray(
                $policyJson['fundamentals_weights'] ?? []
            ),
            riskThresholds: RiskThresholds::fromArray(
                $policyJson['risk_thresholds'] ?? []
            ),
        );
    }
}
```

**Fields:**

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `buyGapThreshold` | float | 15.0 | Minimum composite gap % for BUY |
| `fairValueThreshold` | float | 5.0 | +/- range for "fair value" |
| `minMetricsForGap` | int | 2 | Minimum metrics to compute composite gap |
| `fundamentalsWeights` | FundamentalsWeights | (defaults) | Weights for fundamentals scoring |
| `riskThresholds` | RiskThresholds | (defaults) | Thresholds for risk factors |

### 3.2 FundamentalsWeights

Weights and thresholds for fundamentals scoring.

**Namespace:** `app\dto\analysis`

```php
namespace app\dto\analysis;

final readonly class FundamentalsWeights
{
    public function __construct(
        public float $revenueGrowthWeight = 0.30,
        public float $marginExpansionWeight = 0.25,
        public float $fcfTrendWeight = 0.25,
        public float $debtReductionWeight = 0.20,
        public float $improvingThreshold = 0.30,
        public float $deterioratingThreshold = -0.30,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            revenueGrowthWeight: $data['revenue_growth_weight'] ?? 0.30,
            marginExpansionWeight: $data['margin_expansion_weight'] ?? 0.25,
            fcfTrendWeight: $data['fcf_trend_weight'] ?? 0.25,
            debtReductionWeight: $data['debt_reduction_weight'] ?? 0.20,
            improvingThreshold: $data['improving_threshold'] ?? 0.30,
            deterioratingThreshold: $data['deteriorating_threshold'] ?? -0.30,
        );
    }
}
```

**Weight Distribution:**

| Component | Default Weight | Calculation |
|-----------|----------------|-------------|
| Revenue Growth | 30% | `(latest - prior) / abs(prior) * 100` |
| Margin Expansion | 25% | `latest EBITDA margin - prior margin` |
| FCF Trend | 25% | `(latest FCF - prior FCF) / abs(prior) * 100` |
| Debt Reduction | 20% | `(prior net_debt - latest) / abs(prior) * 100` |

### 3.3 RiskThresholds

Thresholds for risk factor assessment.

**Namespace:** `app\dto\analysis`

```php
namespace app\dto\analysis;

final readonly class RiskThresholds
{
    public function __construct(
        // Leverage: Net Debt / EBITDA
        public float $leverageAcceptable = 2.0,
        public float $leverageElevated = 4.0,
        public float $leverageWeight = 0.40,

        // Liquidity: Cash / Total Debt
        public float $liquidityAcceptable = 0.20,
        public float $liquidityElevated = 0.10,
        public float $liquidityWeight = 0.30,

        // FCF Coverage: FCF / Net Debt
        public float $fcfCoverageAcceptable = 0.15,
        public float $fcfCoverageElevated = 0.05,
        public float $fcfCoverageWeight = 0.30,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            leverageAcceptable: $data['leverage_acceptable'] ?? 2.0,
            leverageElevated: $data['leverage_elevated'] ?? 4.0,
            leverageWeight: $data['leverage_weight'] ?? 0.40,
            liquidityAcceptable: $data['liquidity_acceptable'] ?? 0.20,
            liquidityElevated: $data['liquidity_elevated'] ?? 0.10,
            liquidityWeight: $data['liquidity_weight'] ?? 0.30,
            fcfCoverageAcceptable: $data['fcf_coverage_acceptable'] ?? 0.15,
            fcfCoverageElevated: $data['fcf_coverage_elevated'] ?? 0.05,
            fcfCoverageWeight: $data['fcf_coverage_weight'] ?? 0.30,
        );
    }
}
```

**Risk Factor Matrix:**

| Factor | Calculation | Acceptable | Elevated | Unacceptable |
|--------|-------------|------------|----------|--------------|
| Leverage | Net Debt / EBITDA | < 2.0x | < 4.0x | >= 4.0x |
| Liquidity | Cash / Total Debt | > 20% | > 10% | <= 10% |
| FCF Coverage | FCF / Net Debt | > 15% | > 5% | <= 5% |

### 3.4 AnalyzeReportRequest

Request DTO for analysis handler.

**Namespace:** `app\dto\analysis`

```php
namespace app\dto\analysis;

use app\dto\IndustryDataPack;

final readonly class AnalyzeReportRequest
{
    public function __construct(
        public IndustryDataPack $dataPack,
        public string $focalTicker,
        public AnalysisThresholds $thresholds,
    ) {}
}
```

### 3.5 AnalyzeReportResult

Result DTO from analysis handler.

**Namespace:** `app\dto\analysis`

```php
namespace app\dto\analysis;

use app\dto\report\ReportDTO;
use app\dto\GateResult;

final readonly class AnalyzeReportResult
{
    public function __construct(
        public bool $success,
        public ?ReportDTO $report,
        public GateResult $gateResult,
        public ?string $errorMessage = null,
    ) {}

    public static function success(ReportDTO $report, GateResult $gateResult): self
    {
        return new self(
            success: true,
            report: $report,
            gateResult: $gateResult,
        );
    }

    public static function failure(GateResult $gateResult, string $message): self
    {
        return new self(
            success: false,
            report: null,
            gateResult: $gateResult,
            errorMessage: $message,
        );
    }
}
```

---

## 4. DTO Layer — Report Output

The `ReportDTO` is the primary output of Phase 2, consumed by Phase 3 (Rendering).

### 4.1 ReportDTO

Root report object containing all analysis results.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use DateTimeImmutable;

final readonly class ReportDTO
{
    public function __construct(
        public string $reportId,
        public DateTimeImmutable $generatedAt,
        public ReportMetadata $metadata,
        public FocalAnalysis $focalAnalysis,
        public PeerComparison $peerComparison,
        public MacroContext $macroContext,
        public AnalysisGateResult $gateResult,
    ) {}

    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'generated_at' => $this->generatedAt->format(DateTimeImmutable::ATOM),
            'metadata' => $this->metadata->toArray(),
            'focal_analysis' => $this->focalAnalysis->toArray(),
            'peer_comparison' => $this->peerComparison->toArray(),
            'macro_context' => $this->macroContext->toArray(),
            'gate_result' => $this->gateResult->toArray(),
        ];
    }
}
```

### 4.2 ReportMetadata

Context information about the report.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class ReportMetadata
{
    public function __construct(
        public string $industryId,
        public string $industryName,
        public string $focalTicker,
        public string $focalName,
        public string $policySlug,
        public string $datapackId,
        public string $sector,
    ) {}

    public function toArray(): array
    {
        return [
            'industry_id' => $this->industryId,
            'industry_name' => $this->industryName,
            'focal_ticker' => $this->focalTicker,
            'focal_name' => $this->focalName,
            'policy_slug' => $this->policySlug,
            'datapack_id' => $this->datapackId,
            'sector' => $this->sector,
        ];
    }
}
```

### 4.3 FocalAnalysis

Complete analysis of the focal company.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\Rating;
use app\enums\RatingRulePath;

final readonly class FocalAnalysis
{
    public function __construct(
        public Rating $rating,
        public RatingRulePath $rulePath,
        public FundamentalsBreakdown $fundamentals,
        public RiskBreakdown $risk,
        public ValuationGapSummary $valuationGap,
        public ValuationSnapshot $currentValuation,
        public FinancialsSummary $financials,
    ) {}

    public function toArray(): array
    {
        return [
            'rating' => $this->rating->value,
            'rule_path' => $this->rulePath->value,
            'fundamentals' => $this->fundamentals->toArray(),
            'risk' => $this->risk->toArray(),
            'valuation_gap' => $this->valuationGap->toArray(),
            'current_valuation' => $this->currentValuation->toArray(),
            'financials' => $this->financials->toArray(),
        ];
    }
}
```

### 4.4 FundamentalsBreakdown

Detailed fundamentals assessment with component scores.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\Fundamentals;

final readonly class FundamentalsBreakdown
{
    /**
     * @param TrendMetric[] $components
     */
    public function __construct(
        public Fundamentals $assessment,
        public float $compositeScore,
        public array $components,
    ) {}

    public function toArray(): array
    {
        return [
            'assessment' => $this->assessment->value,
            'composite_score' => round($this->compositeScore, 4),
            'components' => array_map(
                fn(TrendMetric $c) => $c->toArray(),
                $this->components
            ),
        ];
    }
}
```

### 4.5 TrendMetric

Single component of fundamentals scoring.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class TrendMetric
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $priorValue,
        public ?float $latestValue,
        public ?float $changePercent,
        public ?float $normalizedScore,
        public float $weight,
        public ?float $weightedScore,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'prior_value' => $this->priorValue,
            'latest_value' => $this->latestValue,
            'change_percent' => $this->changePercent !== null
                ? round($this->changePercent, 2)
                : null,
            'normalized_score' => $this->normalizedScore !== null
                ? round($this->normalizedScore, 4)
                : null,
            'weight' => $this->weight,
            'weighted_score' => $this->weightedScore !== null
                ? round($this->weightedScore, 4)
                : null,
        ];
    }
}
```

### 4.6 RiskBreakdown

Detailed risk assessment with factor scores.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\Risk;

final readonly class RiskBreakdown
{
    /**
     * @param RiskFactor[] $factors
     */
    public function __construct(
        public Risk $assessment,
        public float $compositeScore,
        public array $factors,
    ) {}

    public function toArray(): array
    {
        return [
            'assessment' => $this->assessment->value,
            'composite_score' => round($this->compositeScore, 4),
            'factors' => array_map(
                fn(RiskFactor $f) => $f->toArray(),
                $this->factors
            ),
        ];
    }
}
```

### 4.7 RiskFactor

Single component of risk assessment.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\Risk;

final readonly class RiskFactor
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $value,
        public Risk $level,
        public float $weight,
        public string $formula,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value !== null ? round($this->value, 4) : null,
            'level' => $this->level->value,
            'weight' => $this->weight,
            'formula' => $this->formula,
        ];
    }
}
```

### 4.8 ValuationGapSummary

Valuation gap analysis vs peers.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\GapDirection;

final readonly class ValuationGapSummary
{
    /**
     * @param MetricGap[] $individualGaps
     */
    public function __construct(
        public ?float $compositeGap,
        public ?GapDirection $direction,
        public array $individualGaps,
        public int $metricsUsed,
    ) {}

    public function toArray(): array
    {
        return [
            'composite_gap' => $this->compositeGap !== null
                ? round($this->compositeGap, 2)
                : null,
            'direction' => $this->direction?->value,
            'individual_gaps' => array_map(
                fn(MetricGap $g) => $g->toArray(),
                $this->individualGaps
            ),
            'metrics_used' => $this->metricsUsed,
        ];
    }
}
```

### 4.9 MetricGap

Single valuation metric gap.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

use app\enums\GapDirection;

final readonly class MetricGap
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $focalValue,
        public ?float $peerAverage,
        public ?float $gapPercent,
        public ?GapDirection $direction,
        public string $interpretation,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'focal_value' => $this->focalValue !== null
                ? round($this->focalValue, 2)
                : null,
            'peer_average' => $this->peerAverage !== null
                ? round($this->peerAverage, 2)
                : null,
            'gap_percent' => $this->gapPercent !== null
                ? round($this->gapPercent, 2)
                : null,
            'direction' => $this->direction?->value,
            'interpretation' => $this->interpretation,
        ];
    }
}
```

**Interpretation Field Values:**
- `"lower_better"` — For P/E, EV/EBITDA (lower focal = undervalued)
- `"higher_better"` — For yields (higher focal = undervalued)

### 4.10 ValuationSnapshot

Current valuation metrics for display.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class ValuationSnapshot
{
    public function __construct(
        public ?float $marketCapBillions,
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $trailingPe,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
        public ?float $netDebtEbitda,
        public ?float $priceToBook,
        public string $asOf,
    ) {}

    public function toArray(): array
    {
        return [
            'market_cap_billions' => $this->marketCapBillions,
            'fwd_pe' => $this->fwdPe,
            'ev_ebitda' => $this->evEbitda,
            'trailing_pe' => $this->trailingPe,
            'fcf_yield_percent' => $this->fcfYieldPercent,
            'div_yield_percent' => $this->divYieldPercent,
            'net_debt_ebitda' => $this->netDebtEbitda,
            'price_to_book' => $this->priceToBook,
            'as_of' => $this->asOf,
        ];
    }
}
```

### 4.11 FinancialsSummary

Historical financials for the focal company.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class FinancialsSummary
{
    /**
     * @param AnnualFinancialRow[] $annualData
     * @param QuarterlyFinancialRow[] $quarterlyData
     */
    public function __construct(
        public array $annualData,
        public array $quarterlyData,
    ) {}

    public function toArray(): array
    {
        return [
            'annual_data' => array_map(
                fn(AnnualFinancialRow $r) => $r->toArray(),
                $this->annualData
            ),
            'quarterly_data' => array_map(
                fn(QuarterlyFinancialRow $r) => $r->toArray(),
                $this->quarterlyData
            ),
        ];
    }
}
```

### 4.12 AnnualFinancialRow

Single year of financial data for display.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class AnnualFinancialRow
{
    public function __construct(
        public int $fiscalYear,
        public ?float $revenueBillions,
        public ?float $ebitdaBillions,
        public ?float $netIncomeBillions,
        public ?float $fcfBillions,
        public ?float $totalAssetsBillions,
        public ?float $totalDebtBillions,
        public ?float $netDebtBillions,
        public ?float $ebitdaMarginPercent,
    ) {}

    public function toArray(): array
    {
        return [
            'fiscal_year' => $this->fiscalYear,
            'revenue_billions' => $this->revenueBillions,
            'ebitda_billions' => $this->ebitdaBillions,
            'net_income_billions' => $this->netIncomeBillions,
            'fcf_billions' => $this->fcfBillions,
            'total_assets_billions' => $this->totalAssetsBillions,
            'total_debt_billions' => $this->totalDebtBillions,
            'net_debt_billions' => $this->netDebtBillions,
            'ebitda_margin_percent' => $this->ebitdaMarginPercent,
        ];
    }
}
```

### 4.13 QuarterlyFinancialRow

Single quarter of financial data for display.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class QuarterlyFinancialRow
{
    public function __construct(
        public string $quarterKey,
        public ?float $revenueBillions,
        public ?float $ebitdaBillions,
        public ?float $netIncomeBillions,
        public ?float $fcfBillions,
    ) {}

    public function toArray(): array
    {
        return [
            'quarter_key' => $this->quarterKey,
            'revenue_billions' => $this->revenueBillions,
            'ebitda_billions' => $this->ebitdaBillions,
            'net_income_billions' => $this->netIncomeBillions,
            'fcf_billions' => $this->fcfBillions,
        ];
    }
}
```

### 4.14 PeerComparison

Peer group context for the analysis.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class PeerComparison
{
    /**
     * @param PeerSummary[] $peers
     */
    public function __construct(
        public int $peerCount,
        public PeerAverages $averages,
        public array $peers,
    ) {}

    public function toArray(): array
    {
        return [
            'peer_count' => $this->peerCount,
            'averages' => $this->averages->toArray(),
            'peers' => array_map(
                fn(PeerSummary $p) => $p->toArray(),
                $this->peers
            ),
        ];
    }
}
```

### 4.15 PeerAverages

Average valuation metrics across peers (excluding focal).

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class PeerAverages
{
    public function __construct(
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
        public ?float $marketCapBillions,
        public int $companiesIncluded,
    ) {}

    public function toArray(): array
    {
        return [
            'fwd_pe' => $this->fwdPe !== null ? round($this->fwdPe, 2) : null,
            'ev_ebitda' => $this->evEbitda !== null ? round($this->evEbitda, 2) : null,
            'fcf_yield_percent' => $this->fcfYieldPercent !== null
                ? round($this->fcfYieldPercent, 2)
                : null,
            'div_yield_percent' => $this->divYieldPercent !== null
                ? round($this->divYieldPercent, 2)
                : null,
            'market_cap_billions' => $this->marketCapBillions !== null
                ? round($this->marketCapBillions, 2)
                : null,
            'companies_included' => $this->companiesIncluded,
        ];
    }
}
```

### 4.16 PeerSummary

Summary of a single peer company.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class PeerSummary
{
    public function __construct(
        public string $ticker,
        public string $name,
        public ?float $marketCapBillions,
        public ?float $fwdPe,
        public ?float $evEbitda,
        public ?float $fcfYieldPercent,
        public ?float $divYieldPercent,
    ) {}

    public function toArray(): array
    {
        return [
            'ticker' => $this->ticker,
            'name' => $this->name,
            'market_cap_billions' => $this->marketCapBillions,
            'fwd_pe' => $this->fwdPe,
            'ev_ebitda' => $this->evEbitda,
            'fcf_yield_percent' => $this->fcfYieldPercent,
            'div_yield_percent' => $this->divYieldPercent,
        ];
    }
}
```

### 4.17 MacroContext

Macro indicators for context.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class MacroContext
{
    public function __construct(
        public ?float $commodityBenchmarkValue,
        public ?string $commodityBenchmarkKey,
        public ?float $sectorIndexValue,
        public ?string $sectorIndexKey,
        public array $indicators,
    ) {}

    public function toArray(): array
    {
        return [
            'commodity_benchmark_value' => $this->commodityBenchmarkValue,
            'commodity_benchmark_key' => $this->commodityBenchmarkKey,
            'sector_index_value' => $this->sectorIndexValue,
            'sector_index_key' => $this->sectorIndexKey,
            'indicators' => $this->indicators,
        ];
    }
}
```

### 4.18 AnalysisGateResult

Validation result from analysis gate.

**Namespace:** `app\dto\report`

```php
namespace app\dto\report;

final readonly class AnalysisGateResult
{
    /**
     * @param string[] $errors
     * @param string[] $warnings
     */
    public function __construct(
        public bool $passed,
        public array $errors,
        public array $warnings,
    ) {}

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
```

---

## 5. Transformers

### 5.1 PeerAverageTransformer

Calculates peer averages from company data.

**Namespace:** `app\transformers`

```php
namespace app\transformers;

use app\dto\CompanyData;
use app\dto\report\PeerAverages;

final class PeerAverageTransformer
{
    /**
     * Calculate average valuations across peers (excluding focal)
     *
     * @param array<string, CompanyData> $companies
     * @param string $focalTicker
     * @return PeerAverages
     */
    public function transform(array $companies, string $focalTicker): PeerAverages
    {
        // Filter out focal company
        $peers = array_filter(
            $companies,
            fn(CompanyData $c) => $c->ticker !== $focalTicker
        );

        if (count($peers) === 0) {
            return new PeerAverages(
                fwdPe: null,
                evEbitda: null,
                fcfYieldPercent: null,
                divYieldPercent: null,
                marketCapBillions: null,
                companiesIncluded: 0,
            );
        }

        return new PeerAverages(
            fwdPe: $this->average($peers, fn($p) => $p->valuation->fwdPe?->value),
            evEbitda: $this->average($peers, fn($p) => $p->valuation->evEbitda?->value),
            fcfYieldPercent: $this->average($peers, fn($p) => $p->valuation->fcfYield?->value),
            divYieldPercent: $this->average($peers, fn($p) => $p->valuation->divYield?->value),
            marketCapBillions: $this->average(
                $peers,
                fn($p) => $p->valuation->marketCap?->getBaseValue() / 1_000_000_000
            ),
            companiesIncluded: count($peers),
        );
    }

    /**
     * Calculate average of non-null values
     *
     * @param array<string, CompanyData> $peers
     * @param callable(CompanyData): ?float $extractor
     * @return ?float
     */
    private function average(array $peers, callable $extractor): ?float
    {
        $values = array_filter(
            array_map($extractor, $peers),
            fn($v) => $v !== null
        );

        if (count($values) === 0) {
            return null;
        }

        return array_sum($values) / count($values);
    }
}
```

**Key Behavior:**
- Always excludes focal company from averages
- Returns null for metrics where no peers have data
- Uses base values (billions) for display consistency

---

## 6. Handlers

### 6.1 Handler Overview

| Handler | Responsibility | Input | Output |
|---------|---------------|-------|--------|
| `AnalyzeReportHandler` | Orchestrates analysis | `AnalyzeReportRequest` | `AnalyzeReportResult` |
| `CalculateGapsHandler` | Valuation gap calculation | DataPack, focal, averages | `ValuationGapSummary` |
| `AssessFundamentalsHandler` | Fundamentals scoring | DataPack, focal, weights | `FundamentalsBreakdown` |
| `AssessRiskHandler` | Risk scoring | DataPack, focal, thresholds | `RiskBreakdown` |
| `DetermineRatingHandler` | Rating decision | Fundamentals, Risk, Gap | Rating + RulePath |

### 6.2 AnalyzeReportHandler

Orchestrates the full analysis pipeline.

**Namespace:** `app\handlers\analysis`

#### Interface

```php
namespace app\handlers\analysis;

use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalyzeReportResult;

interface AnalyzeReportInterface
{
    public function handle(AnalyzeReportRequest $request): AnalyzeReportResult;
}
```

#### Implementation

```php
namespace app\handlers\analysis;

use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalyzeReportResult;
use app\dto\report\ReportDTO;
use app\dto\report\ReportMetadata;
use app\dto\report\FocalAnalysis;
use app\dto\report\PeerComparison;
use app\dto\report\MacroContext;
use app\dto\report\AnalysisGateResult;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidatorInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class AnalyzeReportHandler implements AnalyzeReportInterface
{
    public function __construct(
        private AnalysisGateValidatorInterface $gateValidator,
        private PeerAverageTransformer $peerAverageTransformer,
        private CalculateGapsInterface $calculateGaps,
        private AssessFundamentalsInterface $assessFundamentals,
        private AssessRiskInterface $assessRisk,
        private DetermineRatingInterface $determineRating,
    ) {}

    public function handle(AnalyzeReportRequest $request): AnalyzeReportResult
    {
        // 1. Gate validation
        $gateResult = $this->gateValidator->validate(
            $request->dataPack,
            $request->focalTicker
        );

        if (!$gateResult->passed) {
            return AnalyzeReportResult::failure(
                $gateResult,
                'Analysis gate validation failed'
            );
        }

        // 2. Extract focal company
        $focal = $request->dataPack->companies[$request->focalTicker] ?? null;
        if ($focal === null) {
            return AnalyzeReportResult::failure(
                $gateResult,
                "Focal company {$request->focalTicker} not found in datapack"
            );
        }

        // 3. Calculate peer averages
        $peerAverages = $this->peerAverageTransformer->transform(
            $request->dataPack->companies,
            $request->focalTicker
        );

        // 4. Calculate valuation gaps
        $valuationGap = $this->calculateGaps->handle(
            $focal,
            $peerAverages,
            $request->thresholds
        );

        // 5. Assess fundamentals
        $fundamentals = $this->assessFundamentals->handle(
            $focal,
            $request->thresholds->fundamentalsWeights
        );

        // 6. Assess risk
        $risk = $this->assessRisk->handle(
            $focal,
            $request->thresholds->riskThresholds
        );

        // 7. Determine rating
        $ratingResult = $this->determineRating->handle(
            $fundamentals,
            $risk,
            $valuationGap,
            $request->thresholds
        );

        // 8. Build output DTOs
        $report = $this->buildReport(
            $request,
            $ratingResult,
            $fundamentals,
            $risk,
            $valuationGap,
            $peerAverages,
            $gateResult
        );

        return AnalyzeReportResult::success($report, $gateResult);
    }

    private function buildReport(
        AnalyzeReportRequest $request,
        RatingDeterminationResult $ratingResult,
        FundamentalsBreakdown $fundamentals,
        RiskBreakdown $risk,
        ValuationGapSummary $valuationGap,
        PeerAverages $peerAverages,
        GateResult $gateResult
    ): ReportDTO {
        $focal = $request->dataPack->companies[$request->focalTicker];

        return new ReportDTO(
            reportId: Uuid::uuid4()->toString(),
            generatedAt: new DateTimeImmutable(),
            metadata: $this->buildMetadata($request),
            focalAnalysis: new FocalAnalysis(
                rating: $ratingResult->rating,
                rulePath: $ratingResult->rulePath,
                fundamentals: $fundamentals,
                risk: $risk,
                valuationGap: $valuationGap,
                currentValuation: $this->buildValuationSnapshot($focal),
                financials: $this->buildFinancialsSummary($focal),
            ),
            peerComparison: $this->buildPeerComparison($request, $peerAverages),
            macroContext: $this->buildMacroContext($request->dataPack->macro),
            gateResult: new AnalysisGateResult(
                passed: $gateResult->passed,
                errors: array_map(fn($e) => $e->message, $gateResult->errors),
                warnings: array_map(fn($w) => $w->message, $gateResult->warnings),
            ),
        );
    }

    // ... helper methods for building sub-DTOs
}
```

### 6.3 CalculateGapsHandler

Calculates valuation gaps vs peer averages.

**Namespace:** `app\handlers\analysis`

#### Interface

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\report\PeerAverages;
use app\dto\report\ValuationGapSummary;
use app\dto\analysis\AnalysisThresholds;

interface CalculateGapsInterface
{
    public function handle(
        CompanyData $focal,
        PeerAverages $peerAverages,
        AnalysisThresholds $thresholds
    ): ValuationGapSummary;
}
```

#### Implementation

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\report\PeerAverages;
use app\dto\report\ValuationGapSummary;
use app\dto\report\MetricGap;
use app\dto\analysis\AnalysisThresholds;
use app\enums\GapDirection;

final class CalculateGapsHandler implements CalculateGapsInterface
{
    /**
     * Metrics where lower is better (undervalued when focal < peers)
     */
    private const LOWER_BETTER_METRICS = ['fwd_pe', 'ev_ebitda'];

    /**
     * Metrics where higher is better (undervalued when focal > peers)
     */
    private const HIGHER_BETTER_METRICS = ['fcf_yield', 'div_yield'];

    public function handle(
        CompanyData $focal,
        PeerAverages $peerAverages,
        AnalysisThresholds $thresholds
    ): ValuationGapSummary {
        $gaps = [];

        // Forward P/E (lower is better)
        $gaps[] = $this->calculateGap(
            'fwd_pe',
            'Forward P/E',
            $focal->valuation->fwdPe?->value,
            $peerAverages->fwdPe,
            true,
            $thresholds->fairValueThreshold
        );

        // EV/EBITDA (lower is better)
        $gaps[] = $this->calculateGap(
            'ev_ebitda',
            'EV/EBITDA',
            $focal->valuation->evEbitda?->value,
            $peerAverages->evEbitda,
            true,
            $thresholds->fairValueThreshold
        );

        // FCF Yield (higher is better)
        $gaps[] = $this->calculateGap(
            'fcf_yield',
            'FCF Yield',
            $focal->valuation->fcfYield?->value,
            $peerAverages->fcfYieldPercent,
            false,
            $thresholds->fairValueThreshold
        );

        // Dividend Yield (higher is better)
        $gaps[] = $this->calculateGap(
            'div_yield',
            'Dividend Yield',
            $focal->valuation->divYield?->value,
            $peerAverages->divYieldPercent,
            false,
            $thresholds->fairValueThreshold
        );

        // Filter to valid gaps only
        $validGaps = array_filter($gaps, fn($g) => $g->gapPercent !== null);
        $gapValues = array_map(fn($g) => $g->gapPercent, $validGaps);

        // Calculate composite
        $compositeGap = null;
        $direction = null;

        if (count($gapValues) >= $thresholds->minMetricsForGap) {
            $compositeGap = array_sum($gapValues) / count($gapValues);
            $direction = $this->determineDirection($compositeGap, $thresholds->fairValueThreshold);
        }

        return new ValuationGapSummary(
            compositeGap: $compositeGap,
            direction: $direction,
            individualGaps: $gaps,
            metricsUsed: count($gapValues),
        );
    }

    private function calculateGap(
        string $key,
        string $label,
        ?float $focalValue,
        ?float $peerAverage,
        bool $lowerIsBetter,
        float $fairValueThreshold
    ): MetricGap {
        if ($focalValue === null || $peerAverage === null || $peerAverage == 0) {
            return new MetricGap(
                key: $key,
                label: $label,
                focalValue: $focalValue,
                peerAverage: $peerAverage,
                gapPercent: null,
                direction: null,
                interpretation: $lowerIsBetter ? 'lower_better' : 'higher_better',
            );
        }

        // Calculate gap percentage
        // For lower_better: gap = (peer - focal) / peer * 100
        //   Positive gap = undervalued (focal is cheaper)
        // For higher_better: gap = (focal - peer) / peer * 100
        //   Positive gap = undervalued (focal has better yield)
        $gap = $lowerIsBetter
            ? (($peerAverage - $focalValue) / $peerAverage) * 100
            : (($focalValue - $peerAverage) / $peerAverage) * 100;

        $direction = $this->determineDirection($gap, $fairValueThreshold);

        return new MetricGap(
            key: $key,
            label: $label,
            focalValue: $focalValue,
            peerAverage: $peerAverage,
            gapPercent: $gap,
            direction: $direction,
            interpretation: $lowerIsBetter ? 'lower_better' : 'higher_better',
        );
    }

    private function determineDirection(float $gap, float $threshold): GapDirection
    {
        return match (true) {
            $gap > $threshold => GapDirection::Undervalued,
            $gap < -$threshold => GapDirection::Overvalued,
            default => GapDirection::Fair,
        };
    }
}
```

### 6.4 AssessFundamentalsHandler

Scores company fundamentals based on YoY trends.

**Namespace:** `app\handlers\analysis`

#### Interface

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\report\FundamentalsBreakdown;
use app\dto\analysis\FundamentalsWeights;

interface AssessFundamentalsInterface
{
    public function handle(
        CompanyData $focal,
        FundamentalsWeights $weights
    ): FundamentalsBreakdown;
}
```

#### Implementation

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\AnnualFinancials;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\TrendMetric;
use app\dto\analysis\FundamentalsWeights;
use app\enums\Fundamentals;

final class AssessFundamentalsHandler implements AssessFundamentalsInterface
{
    public function handle(
        CompanyData $focal,
        FundamentalsWeights $weights
    ): FundamentalsBreakdown {
        // Get latest 2 years of annual data
        $annualData = $focal->financials->annualData;
        usort($annualData, fn($a, $b) => $b->fiscalYear <=> $a->fiscalYear);

        if (count($annualData) < 2) {
            return $this->insufficientData($weights);
        }

        $latest = $annualData[0];
        $prior = $annualData[1];

        // Calculate each component
        $components = [
            $this->calculateRevenueGrowth($prior, $latest, $weights->revenueGrowthWeight),
            $this->calculateMarginExpansion($prior, $latest, $weights->marginExpansionWeight),
            $this->calculateFcfTrend($prior, $latest, $weights->fcfTrendWeight),
            $this->calculateDebtReduction($prior, $latest, $weights->debtReductionWeight),
        ];

        // Calculate composite score
        $weightedScores = array_filter(
            array_map(fn($c) => $c->weightedScore, $components),
            fn($s) => $s !== null
        );

        $compositeScore = count($weightedScores) > 0
            ? array_sum($weightedScores) / array_sum(
                array_map(fn($c) => $c->normalizedScore !== null ? $c->weight : 0, $components)
            )
            : 0.0;

        // Determine assessment
        $assessment = match (true) {
            $compositeScore >= $weights->improvingThreshold => Fundamentals::Improving,
            $compositeScore <= $weights->deterioratingThreshold => Fundamentals::Deteriorating,
            default => Fundamentals::Mixed,
        };

        return new FundamentalsBreakdown(
            assessment: $assessment,
            compositeScore: $compositeScore,
            components: $components,
        );
    }

    private function calculateRevenueGrowth(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorValue = $prior->revenue?->getBaseValue();
        $latestValue = $latest->revenue?->getBaseValue();

        return $this->buildTrendMetric(
            'revenue_growth',
            'Revenue Growth',
            $priorValue,
            $latestValue,
            $weight
        );
    }

    private function calculateMarginExpansion(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorMargin = $this->calculateEbitdaMargin($prior);
        $latestMargin = $this->calculateEbitdaMargin($latest);

        if ($priorMargin === null || $latestMargin === null) {
            return new TrendMetric(
                key: 'margin_expansion',
                label: 'EBITDA Margin',
                priorValue: $priorMargin,
                latestValue: $latestMargin,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        // Margin change is in percentage points, not percent change
        $change = $latestMargin - $priorMargin;
        $normalizedScore = $this->normalizeMarginChange($change);

        return new TrendMetric(
            key: 'margin_expansion',
            label: 'EBITDA Margin',
            priorValue: $priorMargin,
            latestValue: $latestMargin,
            changePercent: $change, // This is pp change, not %
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    private function calculateFcfTrend(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorValue = $prior->freeCashFlow?->getBaseValue();
        $latestValue = $latest->freeCashFlow?->getBaseValue();

        return $this->buildTrendMetric(
            'fcf_trend',
            'Free Cash Flow',
            $priorValue,
            $latestValue,
            $weight
        );
    }

    private function calculateDebtReduction(
        AnnualFinancials $prior,
        AnnualFinancials $latest,
        float $weight
    ): TrendMetric {
        $priorDebt = $prior->netDebt?->getBaseValue();
        $latestDebt = $latest->netDebt?->getBaseValue();

        if ($priorDebt === null || $latestDebt === null || $priorDebt == 0) {
            return new TrendMetric(
                key: 'debt_reduction',
                label: 'Net Debt Reduction',
                priorValue: $priorDebt,
                latestValue: $latestDebt,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        // Debt reduction: positive change = good (debt went down)
        $changePercent = (($priorDebt - $latestDebt) / abs($priorDebt)) * 100;
        $normalizedScore = $this->normalizeGrowthChange($changePercent);

        return new TrendMetric(
            key: 'debt_reduction',
            label: 'Net Debt Reduction',
            priorValue: $priorDebt,
            latestValue: $latestDebt,
            changePercent: $changePercent,
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    private function buildTrendMetric(
        string $key,
        string $label,
        ?float $priorValue,
        ?float $latestValue,
        float $weight
    ): TrendMetric {
        if ($priorValue === null || $latestValue === null || $priorValue == 0) {
            return new TrendMetric(
                key: $key,
                label: $label,
                priorValue: $priorValue,
                latestValue: $latestValue,
                changePercent: null,
                normalizedScore: null,
                weight: $weight,
                weightedScore: null,
            );
        }

        $changePercent = (($latestValue - $priorValue) / abs($priorValue)) * 100;
        $normalizedScore = $this->normalizeGrowthChange($changePercent);

        return new TrendMetric(
            key: $key,
            label: $label,
            priorValue: $priorValue,
            latestValue: $latestValue,
            changePercent: $changePercent,
            normalizedScore: $normalizedScore,
            weight: $weight,
            weightedScore: $normalizedScore * $weight,
        );
    }

    /**
     * Normalize growth percentage to [-1, +1]
     */
    private function normalizeGrowthChange(float $changePercent): float
    {
        return match (true) {
            $changePercent > 20 => 1.0,
            $changePercent > 10 => 0.5,
            $changePercent > -10 => 0.0,
            $changePercent > -20 => -0.5,
            default => -1.0,
        };
    }

    /**
     * Normalize margin change (pp) to [-1, +1]
     */
    private function normalizeMarginChange(float $changePp): float
    {
        return match (true) {
            $changePp > 3 => 1.0,
            $changePp > 1 => 0.5,
            $changePp > -1 => 0.0,
            $changePp > -3 => -0.5,
            default => -1.0,
        };
    }

    private function calculateEbitdaMargin(AnnualFinancials $annual): ?float
    {
        $revenue = $annual->revenue?->getBaseValue();
        $ebitda = $annual->ebitda?->getBaseValue();

        if ($revenue === null || $ebitda === null || $revenue == 0) {
            return null;
        }

        return ($ebitda / $revenue) * 100;
    }

    private function insufficientData(FundamentalsWeights $weights): FundamentalsBreakdown
    {
        return new FundamentalsBreakdown(
            assessment: Fundamentals::Mixed,
            compositeScore: 0.0,
            components: [],
        );
    }
}
```

### 6.5 AssessRiskHandler

Scores company risk based on balance sheet metrics.

**Namespace:** `app\handlers\analysis`

#### Interface

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\report\RiskBreakdown;
use app\dto\analysis\RiskThresholds;

interface AssessRiskInterface
{
    public function handle(
        CompanyData $focal,
        RiskThresholds $thresholds
    ): RiskBreakdown;
}
```

#### Implementation

```php
namespace app\handlers\analysis;

use app\dto\CompanyData;
use app\dto\AnnualFinancials;
use app\dto\report\RiskBreakdown;
use app\dto\report\RiskFactor;
use app\dto\analysis\RiskThresholds;
use app\enums\Risk;

final class AssessRiskHandler implements AssessRiskInterface
{
    public function handle(
        CompanyData $focal,
        RiskThresholds $thresholds
    ): RiskBreakdown {
        // Get latest year of annual data
        $annualData = $focal->financials->annualData;
        usort($annualData, fn($a, $b) => $b->fiscalYear <=> $a->fiscalYear);

        if (count($annualData) === 0) {
            return $this->insufficientData();
        }

        $latest = $annualData[0];

        // Calculate each factor
        $factors = [
            $this->assessLeverage($latest, $thresholds),
            $this->assessLiquidity($latest, $thresholds),
            $this->assessFcfCoverage($latest, $thresholds),
        ];

        // Check for any unacceptable factor (immediate fail)
        foreach ($factors as $factor) {
            if ($factor->level === Risk::Unacceptable) {
                return new RiskBreakdown(
                    assessment: Risk::Unacceptable,
                    compositeScore: -1.0,
                    factors: $factors,
                );
            }
        }

        // Calculate weighted score
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($factors as $factor) {
            if ($factor->value !== null) {
                $score = match ($factor->level) {
                    Risk::Acceptable => 1.0,
                    Risk::Elevated => 0.0,
                    Risk::Unacceptable => -1.0,
                };
                $weightedSum += $score * $factor->weight;
                $totalWeight += $factor->weight;
            }
        }

        $compositeScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        // Determine assessment
        $assessment = match (true) {
            $compositeScore >= 0.5 => Risk::Acceptable,
            $compositeScore >= -0.5 => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskBreakdown(
            assessment: $assessment,
            compositeScore: $compositeScore,
            factors: $factors,
        );
    }

    private function assessLeverage(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $netDebt = $latest->netDebt?->getBaseValue();
        $ebitda = $latest->ebitda?->getBaseValue();

        if ($netDebt === null || $ebitda === null || $ebitda == 0) {
            return new RiskFactor(
                key: 'leverage',
                label: 'Net Debt / EBITDA',
                value: null,
                level: Risk::Elevated, // Conservative default
                weight: $thresholds->leverageWeight,
                formula: 'net_debt / ebitda',
            );
        }

        $ratio = $netDebt / $ebitda;
        $level = match (true) {
            $ratio < $thresholds->leverageAcceptable => Risk::Acceptable,
            $ratio < $thresholds->leverageElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'leverage',
            label: 'Net Debt / EBITDA',
            value: $ratio,
            level: $level,
            weight: $thresholds->leverageWeight,
            formula: 'net_debt / ebitda',
        );
    }

    private function assessLiquidity(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $cash = $latest->cashAndEquivalents?->getBaseValue();
        $totalDebt = $latest->totalDebt?->getBaseValue();

        if ($cash === null || $totalDebt === null || $totalDebt == 0) {
            return new RiskFactor(
                key: 'liquidity',
                label: 'Cash / Total Debt',
                value: null,
                level: Risk::Elevated,
                weight: $thresholds->liquidityWeight,
                formula: 'cash_and_equivalents / total_debt',
            );
        }

        $ratio = $cash / $totalDebt;
        $level = match (true) {
            $ratio > $thresholds->liquidityAcceptable => Risk::Acceptable,
            $ratio > $thresholds->liquidityElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'liquidity',
            label: 'Cash / Total Debt',
            value: $ratio,
            level: $level,
            weight: $thresholds->liquidityWeight,
            formula: 'cash_and_equivalents / total_debt',
        );
    }

    private function assessFcfCoverage(
        AnnualFinancials $latest,
        RiskThresholds $thresholds
    ): RiskFactor {
        $fcf = $latest->freeCashFlow?->getBaseValue();
        $netDebt = $latest->netDebt?->getBaseValue();

        if ($fcf === null || $netDebt === null || $netDebt == 0) {
            return new RiskFactor(
                key: 'fcf_coverage',
                label: 'FCF / Net Debt',
                value: null,
                level: Risk::Elevated,
                weight: $thresholds->fcfCoverageWeight,
                formula: 'free_cash_flow / net_debt',
            );
        }

        $ratio = $fcf / $netDebt;
        $level = match (true) {
            $ratio > $thresholds->fcfCoverageAcceptable => Risk::Acceptable,
            $ratio > $thresholds->fcfCoverageElevated => Risk::Elevated,
            default => Risk::Unacceptable,
        };

        return new RiskFactor(
            key: 'fcf_coverage',
            label: 'FCF / Net Debt',
            value: $ratio,
            level: $level,
            weight: $thresholds->fcfCoverageWeight,
            formula: 'free_cash_flow / net_debt',
        );
    }

    private function insufficientData(): RiskBreakdown
    {
        return new RiskBreakdown(
            assessment: Risk::Elevated,
            compositeScore: 0.0,
            factors: [],
        );
    }
}
```

### 6.6 DetermineRatingHandler

Applies rating decision tree.

**Namespace:** `app\handlers\analysis`

#### Interface

```php
namespace app\handlers\analysis;

use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\dto\analysis\AnalysisThresholds;

interface DetermineRatingInterface
{
    public function handle(
        FundamentalsBreakdown $fundamentals,
        RiskBreakdown $risk,
        ValuationGapSummary $valuationGap,
        AnalysisThresholds $thresholds
    ): RatingDeterminationResult;
}
```

#### RatingDeterminationResult DTO

```php
namespace app\dto\analysis;

use app\enums\Rating;
use app\enums\RatingRulePath;

final readonly class RatingDeterminationResult
{
    public function __construct(
        public Rating $rating,
        public RatingRulePath $rulePath,
    ) {}
}
```

#### Implementation

```php
namespace app\handlers\analysis;

use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;
use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\RatingDeterminationResult;
use app\enums\Rating;
use app\enums\RatingRulePath;
use app\enums\Fundamentals;
use app\enums\Risk;

final class DetermineRatingHandler implements DetermineRatingInterface
{
    public function handle(
        FundamentalsBreakdown $fundamentals,
        RiskBreakdown $risk,
        ValuationGapSummary $valuationGap,
        AnalysisThresholds $thresholds
    ): RatingDeterminationResult {
        // Rule 1: Deteriorating fundamentals → SELL
        if ($fundamentals->assessment === Fundamentals::Deteriorating) {
            return new RatingDeterminationResult(
                rating: Rating::Sell,
                rulePath: RatingRulePath::SellFundamentals,
            );
        }

        // Rule 2: Unacceptable risk → SELL
        if ($risk->assessment === Risk::Unacceptable) {
            return new RatingDeterminationResult(
                rating: Rating::Sell,
                rulePath: RatingRulePath::SellRisk,
            );
        }

        // Rule 3: Insufficient valuation data → HOLD
        if ($valuationGap->compositeGap === null) {
            return new RatingDeterminationResult(
                rating: Rating::Hold,
                rulePath: RatingRulePath::HoldInsufficientData,
            );
        }

        // Rule 4: All conditions met → BUY
        if (
            $valuationGap->compositeGap > $thresholds->buyGapThreshold
            && $fundamentals->assessment === Fundamentals::Improving
            && $risk->assessment === Risk::Acceptable
        ) {
            return new RatingDeterminationResult(
                rating: Rating::Buy,
                rulePath: RatingRulePath::BuyAllConditions,
            );
        }

        // Default: HOLD
        return new RatingDeterminationResult(
            rating: Rating::Hold,
            rulePath: RatingRulePath::HoldDefault,
        );
    }
}
```

---

## 7. The Analysis Gate

### 7.1 AnalysisGateValidator

Validates datapack completeness before analysis.

**Namespace:** `app\validators`

#### Interface

```php
namespace app\validators;

use app\dto\IndustryDataPack;
use app\dto\GateResult;

interface AnalysisGateValidatorInterface
{
    public function validate(IndustryDataPack $dataPack, string $focalTicker): GateResult;
}
```

#### Implementation

```php
namespace app\validators;

use app\dto\IndustryDataPack;
use app\dto\GateResult;
use app\dto\GateError;
use app\dto\GateWarning;
use app\dto\CompanyData;

final class AnalysisGateValidator implements AnalysisGateValidatorInterface
{
    private const ERROR_FOCAL_NOT_FOUND = 'FOCAL_NOT_FOUND';
    private const ERROR_INSUFFICIENT_ANNUAL_DATA = 'INSUFFICIENT_ANNUAL_DATA';
    private const ERROR_MISSING_VALUATION = 'MISSING_VALUATION';
    private const ERROR_NO_PEERS = 'NO_PEERS';

    private const WARNING_LOW_PEER_COUNT = 'LOW_PEER_COUNT';
    private const WARNING_STALE_DATA = 'STALE_DATA';
    private const WARNING_MISSING_OPTIONAL = 'MISSING_OPTIONAL';

    private const MIN_ANNUAL_YEARS = 2;
    private const MIN_PEER_COUNT = 2;
    private const STALE_DAYS = 30;

    public function validate(IndustryDataPack $dataPack, string $focalTicker): GateResult
    {
        $errors = [];
        $warnings = [];

        // 1. Focal company exists
        if (!isset($dataPack->companies[$focalTicker])) {
            $errors[] = new GateError(
                code: self::ERROR_FOCAL_NOT_FOUND,
                message: "Focal company {$focalTicker} not found in datapack",
                path: "companies.{$focalTicker}",
            );
            return new GateResult(false, $errors, $warnings);
        }

        $focal = $dataPack->companies[$focalTicker];

        // 2. Sufficient annual data for trend analysis
        $annualCount = count($focal->financials->annualData);
        if ($annualCount < self::MIN_ANNUAL_YEARS) {
            $errors[] = new GateError(
                code: self::ERROR_INSUFFICIENT_ANNUAL_DATA,
                message: "Focal company has {$annualCount} years of annual data, minimum is " . self::MIN_ANNUAL_YEARS,
                path: "companies.{$focalTicker}.financials.annualData",
            );
        }

        // 3. Valuation data present
        if ($focal->valuation->marketCap === null) {
            $errors[] = new GateError(
                code: self::ERROR_MISSING_VALUATION,
                message: "Focal company missing market cap",
                path: "companies.{$focalTicker}.valuation.marketCap",
            );
        }

        // 4. Peer count
        $peerCount = count($dataPack->companies) - 1;
        if ($peerCount === 0) {
            $errors[] = new GateError(
                code: self::ERROR_NO_PEERS,
                message: "No peer companies found for comparison",
            );
        } elseif ($peerCount < self::MIN_PEER_COUNT) {
            $warnings[] = new GateWarning(
                code: self::WARNING_LOW_PEER_COUNT,
                message: "Only {$peerCount} peer(s) available, recommend minimum " . self::MIN_PEER_COUNT,
            );
        }

        // 5. Data freshness (warning only)
        $collectedAt = $dataPack->collectedAt;
        $daysSinceCollection = (new \DateTimeImmutable())->diff($collectedAt)->days;
        if ($daysSinceCollection > self::STALE_DAYS) {
            $warnings[] = new GateWarning(
                code: self::WARNING_STALE_DATA,
                message: "Data is {$daysSinceCollection} days old (collected {$collectedAt->format('Y-m-d')})",
            );
        }

        return new GateResult(
            passed: count($errors) === 0,
            errors: $errors,
            warnings: $warnings,
        );
    }
}
```

**Validation Rules:**

| Check | Severity | Description |
|-------|----------|-------------|
| Focal exists | Error | Focal company must be in datapack |
| Annual data | Error | Minimum 2 years for trend analysis |
| Market cap | Error | Required for valuation |
| Peers exist | Error | At least 1 peer required |
| Peer count | Warning | Recommend 2+ peers |
| Data freshness | Warning | Flag data older than 30 days |

---

## 8. CLI Interface

### 8.1 AnalyzeController

CLI command for running analysis.

**Namespace:** `app\commands`

```php
namespace app\commands;

use app\handlers\analysis\AnalyzeReportInterface;
use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalysisThresholds;
use app\queries\IndustryDataPackQuery;
use app\queries\CollectionPolicyQuery;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class AnalyzeController extends Controller
{
    public function __construct(
        $id,
        $module,
        private AnalyzeReportInterface $analyzeHandler,
        private IndustryDataPackQuery $dataPackQuery,
        private CollectionPolicyQuery $policyQuery,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Analyze a peer group and generate report
     *
     * @param string $peerGroup Peer group slug
     * @param string $focal Focal company ticker
     * @return int Exit code
     */
    public function actionPeerGroup(string $peerGroup, string $focal): int
    {
        $this->stdout("Analyzing peer group: {$peerGroup}\n", Console::FG_CYAN);
        $this->stdout("Focal company: {$focal}\n\n");

        // 1. Load datapack
        $dataPack = $this->dataPackQuery->getLatestForPeerGroup($peerGroup);
        if ($dataPack === null) {
            $this->stderr("No datapack found for peer group: {$peerGroup}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // 2. Load policy thresholds
        $policy = $this->policyQuery->getBySlug($peerGroup);
        $thresholds = $policy !== null
            ? AnalysisThresholds::fromPolicy($policy->analysis_thresholds ?? [])
            : new AnalysisThresholds();

        // 3. Run analysis
        $request = new AnalyzeReportRequest(
            dataPack: $dataPack,
            focalTicker: $focal,
            thresholds: $thresholds,
        );

        $result = $this->analyzeHandler->handle($request);

        // 4. Output results
        if (!$result->success) {
            $this->stderr("Analysis failed: {$result->errorMessage}\n", Console::FG_RED);
            foreach ($result->gateResult->errors as $error) {
                $this->stderr("  - {$error->message}\n");
            }
            return ExitCode::DATAERR;
        }

        $report = $result->report;
        $this->stdout("Rating: {$report->focalAnalysis->rating->value}\n", Console::FG_GREEN);
        $this->stdout("Rule Path: {$report->focalAnalysis->rulePath->value}\n");
        $this->stdout("Fundamentals: {$report->focalAnalysis->fundamentals->assessment->value}\n");
        $this->stdout("Risk: {$report->focalAnalysis->risk->assessment->value}\n");

        if ($report->focalAnalysis->valuationGap->compositeGap !== null) {
            $gap = round($report->focalAnalysis->valuationGap->compositeGap, 2);
            $this->stdout("Composite Gap: {$gap}%\n");
        }

        // Warnings
        foreach ($result->gateResult->warnings as $warning) {
            $this->stdout("Warning: {$warning->message}\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }
}
```

**Usage:**

```bash
# Analyze AAPL against us-tech-giants peer group
php yii analyze/peer-group us-tech-giants --focal=AAPL

# Analyze XOM against us-energy-majors peer group
php yii analyze/peer-group us-energy-majors --focal=XOM
```

---

## 9. Algorithm Reference

### 9.1 Fundamentals Scoring Algorithm

**Input:** Latest 2 years of annual financial data

**Components:**

| Metric | Formula | Normalization |
|--------|---------|---------------|
| Revenue Growth | `(latest - prior) / abs(prior) * 100` | [-1, +1] by thresholds |
| Margin Expansion | `latest_margin - prior_margin` | [-1, +1] by thresholds |
| FCF Trend | `(latest - prior) / abs(prior) * 100` | [-1, +1] by thresholds |
| Debt Reduction | `(prior - latest) / abs(prior) * 100` | [-1, +1] by thresholds |

**Normalization Thresholds (Growth):**

| Change % | Score |
|----------|-------|
| > 20% | +1.0 |
| > 10% | +0.5 |
| > -10% | 0.0 |
| > -20% | -0.5 |
| <= -20% | -1.0 |

**Normalization Thresholds (Margin pp):**

| Change pp | Score |
|-----------|-------|
| > 3pp | +1.0 |
| > 1pp | +0.5 |
| > -1pp | 0.0 |
| > -3pp | -0.5 |
| <= -3pp | -1.0 |

**Composite Calculation:**

```
composite = Σ(score × weight) / Σ(weights where score is not null)
```

**Assessment Determination:**

```
IF composite >= 0.30: Improving
ELSE IF composite <= -0.30: Deteriorating
ELSE: Mixed
```

### 9.2 Risk Scoring Algorithm

**Input:** Latest year balance sheet data

**Factors:**

| Factor | Formula | Acceptable | Elevated | Unacceptable |
|--------|---------|------------|----------|--------------|
| Leverage | Net Debt / EBITDA | < 2.0x | < 4.0x | >= 4.0x |
| Liquidity | Cash / Total Debt | > 20% | > 10% | <= 10% |
| FCF Coverage | FCF / Net Debt | > 15% | > 5% | <= 5% |

**Algorithm:**

1. Calculate each factor ratio
2. Assign level based on thresholds
3. If ANY factor is Unacceptable → overall Unacceptable
4. Otherwise, calculate weighted score:
   - Acceptable = +1.0
   - Elevated = 0.0
   - Unacceptable = -1.0
5. Determine assessment from composite:
   - >= 0.5 → Acceptable
   - >= -0.5 → Elevated
   - < -0.5 → Unacceptable

### 9.3 Valuation Gap Algorithm

**Metrics:**

| Metric | Direction | Gap Formula |
|--------|-----------|-------------|
| Forward P/E | Lower is better | `(peer_avg - focal) / peer_avg * 100` |
| EV/EBITDA | Lower is better | `(peer_avg - focal) / peer_avg * 100` |
| FCF Yield | Higher is better | `(focal - peer_avg) / peer_avg * 100` |
| Div Yield | Higher is better | `(focal - peer_avg) / peer_avg * 100` |

**Composite Calculation:**

```
IF valid_gaps.count >= minMetricsForGap:
    composite = average(valid_gaps)
ELSE:
    composite = null
```

**Direction Determination:**

```
IF gap > fairValueThreshold: Undervalued
ELSE IF gap < -fairValueThreshold: Overvalued
ELSE: Fair
```

### 9.4 Rating Decision Tree

```
1. IF fundamentals == Deteriorating:
   → SELL (SELL_FUNDAMENTALS)

2. ELSE IF risk == Unacceptable:
   → SELL (SELL_RISK)

3. ELSE IF composite_gap is null:
   → HOLD (HOLD_INSUFFICIENT_DATA)

4. ELSE IF composite_gap > buyGapThreshold
        AND fundamentals == Improving
        AND risk == Acceptable:
   → BUY (BUY_ALL_CONDITIONS)

5. ELSE:
   → HOLD (HOLD_DEFAULT)
```

---

## 10. Error Handling Strategy

### 10.1 Error Categories

| Category | Handler | Example |
|----------|---------|---------|
| Gate Failure | Return failure result | Missing focal company |
| Missing Data | Use null, skip calculation | No EBITDA for margin |
| Division by Zero | Return null for metric | Zero peer average |

### 10.2 Null Handling

All calculations handle null inputs gracefully:

```php
// Example: Gap calculation with null handling
if ($focalValue === null || $peerAverage === null || $peerAverage == 0) {
    return new MetricGap(
        // ... with gapPercent: null
    );
}
```

### 10.3 Insufficient Data Behavior

| Scenario | Behavior |
|----------|----------|
| < 2 years annual data | Fundamentals = Mixed, score = 0 |
| No balance sheet data | Risk = Elevated (conservative) |
| < minMetrics valid gaps | Composite gap = null, triggers HOLD_INSUFFICIENT_DATA |
| No peers | Gate failure (cannot analyze) |

---

## 11. Testing Strategy

### 11.1 Unit Tests

**Handlers:**

```bash
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/handlers/analysis/
```

| Test File | Coverage |
|-----------|----------|
| `CalculateGapsHandlerTest.php` | Gap calculation, direction, null handling |
| `AssessFundamentalsHandlerTest.php` | Scoring, normalization, insufficient data |
| `AssessRiskHandlerTest.php` | Factor scoring, unacceptable override |
| `DetermineRatingHandlerTest.php` | All decision tree paths |
| `AnalyzeReportHandlerTest.php` | Orchestration, gate integration |

**Transformers:**

```bash
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/transformers/PeerAverageTransformerTest.php
```

| Scenario | Test Case |
|----------|-----------|
| Normal peers | Average calculation |
| Focal exclusion | Focal not included in average |
| All null values | Returns null for metric |
| Single peer | Works with one peer |
| No peers | Returns empty averages |

**Validators:**

```bash
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/validators/AnalysisGateValidatorTest.php
```

| Scenario | Expected |
|----------|----------|
| Valid datapack | Gate passes |
| Missing focal | Error: FOCAL_NOT_FOUND |
| 1 year data | Error: INSUFFICIENT_ANNUAL_DATA |
| No market cap | Error: MISSING_VALUATION |
| No peers | Error: NO_PEERS |
| 1 peer | Warning: LOW_PEER_COUNT |
| Stale data | Warning: STALE_DATA |

### 11.2 Integration Tests

```bash
docker exec aimm_yii vendor/bin/codecept run integration tests/integration/handlers/analysis/
```

**Full Pipeline Test:**

1. Load real datapack from database
2. Run complete analysis
3. Verify ReportDTO structure
4. Verify rating matches expected path

### 11.3 Test Data Scenarios

| Scenario | Expected Rating | Rule Path |
|----------|----------------|-----------|
| Strong fundamentals, low risk, undervalued | BUY | BUY_ALL_CONDITIONS |
| Declining revenue, low FCF | SELL | SELL_FUNDAMENTALS |
| High leverage (> 4x) | SELL | SELL_RISK |
| Insufficient valuation data | HOLD | HOLD_INSUFFICIENT_DATA |
| Fair value, mixed fundamentals | HOLD | HOLD_DEFAULT |

---

## 12. Implementation Plan

### 12.1 Implementation Order

**Step 1: Enums** (no dependencies)
- `Rating.php`
- `RatingRulePath.php`
- `Fundamentals.php`
- `Risk.php`
- `GapDirection.php`

**Step 2: Config DTOs** (depends on enums)
- `AnalysisThresholds.php`
- `FundamentalsWeights.php`
- `RiskThresholds.php`

**Step 3: Report DTOs — Leaf** (no dependencies)
- `TrendMetric.php`
- `RiskFactor.php`
- `MetricGap.php`
- `AnnualFinancialRow.php`
- `QuarterlyFinancialRow.php`
- `PeerSummary.php`

**Step 4: Report DTOs — Mid** (depends on leaf)
- `FundamentalsBreakdown.php`
- `RiskBreakdown.php`
- `ValuationGapSummary.php`
- `ValuationSnapshot.php`
- `FinancialsSummary.php`
- `PeerAverages.php`
- `MacroContext.php`

**Step 5: Report DTOs — Parent** (depends on mid)
- `FocalAnalysis.php`
- `PeerComparison.php`
- `ReportMetadata.php`
- `AnalysisGateResult.php`

**Step 6: Report DTOs — Root** (depends on parent)
- `ReportDTO.php`

**Step 7: Analysis DTOs** (depends on enums)
- `AnalyzeReportRequest.php`
- `AnalyzeReportResult.php`
- `RatingDeterminationResult.php`

**Step 8: Transformer** (depends on DTOs)
- `PeerAverageTransformer.php`

**Step 9: Validator** (depends on DTOs)
- `AnalysisGateValidator.php`

**Step 10: Handlers** (dependency order)
1. `CalculateGapsHandler.php`
2. `AssessFundamentalsHandler.php`
3. `AssessRiskHandler.php`
4. `DetermineRatingHandler.php`
5. `AnalyzeReportHandler.php`

**Step 11: CLI**
- `AnalyzeController.php`

**Step 12: Migration** (optional, for per-policy thresholds)
- `m260108_xxxxxx_add_analysis_thresholds_to_policy.php`

### 12.2 File Summary

| Category | Count | Location |
|----------|-------|----------|
| Enums | 5 | `yii/src/enums/` |
| Analysis DTOs | 6 | `yii/src/dto/analysis/` |
| Report DTOs | 18 | `yii/src/dto/report/` |
| Handlers | 10 | `yii/src/handlers/analysis/` |
| Transformers | 1 | `yii/src/transformers/` |
| Validators | 2 | `yii/src/validators/` |
| CLI | 1 | `yii/src/commands/` |
| Migration | 1 | `yii/migrations/` |

**Total: ~44 new files**

### 12.3 Verification Commands

```bash
# Run all unit tests
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/handlers/analysis/
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/transformers/PeerAverageTransformerTest.php
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/validators/AnalysisGateValidatorTest.php

# Run linter
docker exec aimm_yii vendor/bin/php-cs-fixer fix

# Integration test
docker exec aimm_yii php yii db/reset --interactive=0
docker exec aimm_yii php yii collect/peer-group us-tech-giants
docker exec aimm_yii php yii analyze/peer-group us-tech-giants --focal=AAPL
```

---

## Appendix A: Critical Files Reference

| Purpose | File |
|---------|------|
| Phase 1 Output | `yii/src/dto/IndustryDataPack.php` |
| Company Data | `yii/src/dto/CompanyData.php` |
| Annual Financials | `yii/src/dto/AnnualFinancials.php` |
| Valuation Data | `yii/src/dto/ValuationData.php` |
| Gate Pattern | `yii/src/validators/CollectionGateValidator.php` |
| Handler Pattern | `yii/src/handlers/collection/CollectIndustryHandler.php` |
| Enum Pattern | `yii/src/enums/CollectionStatus.php` |
| CLI Pattern | `yii/src/commands/CollectController.php` |

---

## Appendix B: JSON Output Example

```json
{
  "report_id": "550e8400-e29b-41d4-a716-446655440000",
  "generated_at": "2026-01-08T10:30:00+00:00",
  "metadata": {
    "industry_id": "us-tech-giants",
    "industry_name": "US Tech Giants",
    "focal_ticker": "AAPL",
    "focal_name": "Apple Inc.",
    "policy_slug": "us-tech-giants",
    "datapack_id": "abc123",
    "sector": "Technology"
  },
  "focal_analysis": {
    "rating": "hold",
    "rule_path": "HOLD_DEFAULT",
    "fundamentals": {
      "assessment": "mixed",
      "composite_score": 0.15,
      "components": [
        {
          "key": "revenue_growth",
          "label": "Revenue Growth",
          "prior_value": 383285000000,
          "latest_value": 391035000000,
          "change_percent": 2.02,
          "normalized_score": 0.0,
          "weight": 0.3,
          "weighted_score": 0.0
        }
      ]
    },
    "risk": {
      "assessment": "acceptable",
      "composite_score": 0.75,
      "factors": [
        {
          "key": "leverage",
          "label": "Net Debt / EBITDA",
          "value": 0.85,
          "level": "acceptable",
          "weight": 0.4,
          "formula": "net_debt / ebitda"
        }
      ]
    },
    "valuation_gap": {
      "composite_gap": 8.5,
      "direction": "fair",
      "individual_gaps": [
        {
          "key": "fwd_pe",
          "label": "Forward P/E",
          "focal_value": 28.5,
          "peer_average": 32.1,
          "gap_percent": 11.2,
          "direction": "undervalued",
          "interpretation": "lower_better"
        }
      ],
      "metrics_used": 3
    }
  },
  "peer_comparison": {
    "peer_count": 4,
    "averages": {
      "fwd_pe": 32.1,
      "ev_ebitda": 18.5,
      "fcf_yield_percent": 3.2,
      "div_yield_percent": 0.8,
      "market_cap_billions": 1850.5,
      "companies_included": 4
    },
    "peers": [
      {
        "ticker": "MSFT",
        "name": "Microsoft Corporation",
        "market_cap_billions": 3100.5
      }
    ]
  },
  "macro_context": {
    "commodity_benchmark_value": null,
    "commodity_benchmark_key": null,
    "sector_index_value": null,
    "sector_index_key": null,
    "indicators": []
  },
  "gate_result": {
    "passed": true,
    "errors": [],
    "warnings": []
  }
}
```
