# Pipeline

The AIMM pipeline consists of three sequential phases, each with validation gates to ensure data quality.

## Phase 1: Collect

**Goal:** Gather financial data for an entire industry.

| Aspect | Description |
|--------|-------------|
| Input | Industry config (JSON) |
| Output | IndustryDataPack (JSON) |
| Collectors | Macro + Company (×N) |
| Gate | Collection Gate |

### What Gets Collected

- **Macro data**: Commodity benchmarks, margin proxies, sector indices
- **Company data** (per company):
  - Valuation metrics (market cap, P/E, EV/EBITDA)
  - Annual financials (revenue, EBITDA, net income)
  - Quarterly financials
  - Operational metrics (industry-specific)

### Collection Gate Checks

- JSON Schema compliance
- All required datapoints present
- All configured companies collected
- Macro data within freshness threshold (10 days)
- Minimum financial history present

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Macro     │    │  Company 1  │    │  Company N  │
│  Collector  │    │  Collector  │    │  Collector  │
└──────┬──────┘    └──────┬──────┘    └──────┬──────┘
       │                  │                  │
       └──────────────────┴──────────────────┘
                          │
                          ▼
               ┌─────────────────────┐
               │  IndustryDataPack   │
               └──────────┬──────────┘
                          │
                          ▼
               ┌─────────────────────┐
               │  COLLECTION GATE    │
               └─────────────────────┘
```

## Phase 2: Analyze

**Goal:** Calculate valuation gaps and determine rating.

| Aspect | Description |
|--------|-------------|
| Input | IndustryDataPack + focal ticker + peer tickers |
| Output | ReportDTO (JSON) |
| Rule | NO external calls (deterministic only) |
| Gate | Analysis Gate |

### Key Calculations

1. **Peer Averages**: Calculate industry averages for each metric
2. **Valuation Gap**: Composite gap from fwd_pe, ev_ebitda, fcf_yield, div_yield
3. **Rating**: BUY/HOLD/SELL based on fundamentals, risk, and valuation gap

### Analysis Gate Checks

- JSON Schema compliance
- Recompute peer averages → must match reported values
- Recompute valuation gap → must match reported value
- Rating rule path consistency

```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│    Gap      │  │   Rating    │  │   Report    │
│ Calculator  │  │ Determiner  │  │   Builder   │
└──────┬──────┘  └──────┬──────┘  └──────┬──────┘
       │                │                │
       └────────────────┴────────────────┘
                        │
                        ▼
             ┌─────────────────────┐
             │     ReportDTO       │
             └──────────┬──────────┘
                        │
                        ▼
             ┌─────────────────────┐
             │   ANALYSIS GATE     │
             └─────────────────────┘
```

## Phase 3: Render

**Goal:** Generate a professional PDF report.

| Aspect | Description |
|--------|-------------|
| Input | ReportDTO (JSON) |
| Output | report.pdf |
| Renderer | Python (ReportLab + matplotlib) |
| Rule | NO business logic |

### Rendering Rules

- Python renderer is "dumb" (no business logic)
- Receives JSON, outputs PDF
- Charts generated from DTO data only

```
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│  ReportLab  │  │ matplotlib  │  │   Layout    │
│    Core     │  │   Charts    │  │   Engine    │
└──────┬──────┘  └──────┬──────┘  └──────┬──────┘
       │                │                │
       └────────────────┴────────────────┘
                        │
                        ▼
             ┌─────────────────────┐
             │     report.pdf      │
             └─────────────────────┘
```

## Full Pipeline Diagram

```
PHASE 1: COLLECT
────────────────
Industry Config ──► Collectors ──► IndustryDataPack ──► Collection Gate
                                                              │
                                                        PASS ─┴─ FAIL
                                                         │
                                                         ▼
PHASE 2: ANALYZE
────────────────
DataPack + Focal + Peers ──► Analysis ──► ReportDTO ──► Analysis Gate
                                                              │
                                                        PASS ─┴─ FAIL
                                                         │
                                                         ▼
PHASE 3: RENDER
───────────────
ReportDTO ──► Python Renderer ──► report.pdf
```
