# AIMM

**Equity intelligence pipeline for smarter investment decisions.**

## Naming Convention

| Context | Name | Example |
|---------|------|---------|
| Human-facing / codename | AIMM | "The AIMM report says HOLD" |
| Repository / package prefix | `aimm-*` | `aimm-collector`, `aimm-analyzer`, `aimm-renderer` |
| Logs / artifacts | `aimm-*` | `aimm-datapack-2025-12-13.json` |
| Namespace (PHP) | `app\` | `app\handlers\CollectIndustryHandler` |

## Project Overview

A three-phase pipeline that generates institutional-grade equity research PDF reports for publicly traded companies. The system collects financial data for an entire industry, analyzes a focal company against its peers, and renders a professional PDF report.

### Core Principle

**Data quality over speed.** Every datapoint must carry full provenance (source URL, retrieval timestamp, extraction method). Validation gates between phases prevent "beautiful but wrong" reports.

### Technology Stack

- **Orchestration**: Yii 2 Framework (PHP 8.5+)
- **PDF Rendering**: Python 3.11+ with ReportLab + matplotlib
- **Schema Validation**: JSON Schema draft-07 via opis/json-schema
- **Process Management**: Symfony Process component
- **Queue**: yii2-queue (optional, for background processing)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PHASE 1: COLLECT                                  │
│                                                                             │
│  Input: Industry config (JSON)                                              │
│  Output: IndustryDataPack (JSON)                                            │
│                                                                             │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   Macro     │    │  Company 1  │    │  Company 2  │    │  Company N  │  │
│  │  Collector  │    │  Collector  │    │  Collector  │    │  Collector  │  │
│  └──────┬──────┘    └──────┬──────┘    └──────┬──────┘    └──────┬──────┘  │
│         │                  │                  │                  │          │
│         └──────────────────┴──────────────────┴──────────────────┘          │
│                                      │                                      │
│                                      ▼                                      │
│                          ┌─────────────────────┐                            │
│                          │  IndustryDataPack   │                            │
│                          │       (JSON)        │                            │
│                          └──────────┬──────────┘                            │
│                                     │                                       │
│                                     ▼                                       │
│                          ┌─────────────────────┐                            │
│                          │  COLLECTION GATE    │                            │
│                          │  - Schema valid?    │                            │
│                          │  - Required data?   │                            │
│                          │  - Macro fresh?     │                            │
│                          └──────────┬──────────┘                            │
│                                     │                                       │
│                            FAIL ◄───┴───► PASS                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PHASE 2: ANALYZE                                  │
│                                                                             │
│  Input: IndustryDataPack + focal_ticker + peer_tickers[]                    │
│  Output: ReportDTO (JSON)                                                   │
│                                                                             │
│  RULES:                                                                     │
│  - NO external calls (deterministic only)                                   │
│  - All calculations recomputable from DataPack                              │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                        AnalysisService                              │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                 │    │
│  │  │    Gap      │  │   Rating    │  │   Report    │                 │    │
│  │  │ Calculator  │  │ Determiner  │  │   Builder   │                 │    │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                 │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                      │                                      │
│                                      ▼                                      │
│                          ┌─────────────────────┐                            │
│                          │     ReportDTO       │                            │
│                          │       (JSON)        │                            │
│                          └──────────┬──────────┘                            │
│                                     │                                       │
│                                     ▼                                       │
│                          ┌─────────────────────┐                            │
│                          │   ANALYSIS GATE     │                            │
│                          │  - Schema valid?    │                            │
│                          │  - Calcs match?     │                            │
│                          │  - Rating consistent│                            │
│                          └──────────┬──────────┘                            │
│                                     │                                       │
│                            FAIL ◄───┴───► PASS                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           PHASE 3: RENDER                                   │
│                                                                             │
│  Input: ReportDTO (JSON)                                                    │
│  Output: PDF file                                                           │
│                                                                             │
│  RULES:                                                                     │
│  - Python renderer is "dumb" (no business logic)                            │
│  - Receives JSON, outputs PDF                                               │
│  - Charts generated from DTO data only                                      │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                    Python PDF Renderer                              │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                 │    │
│  │  │  ReportLab  │  │ matplotlib  │  │   Layout    │                 │    │
│  │  │    Core     │  │   Charts    │  │   Engine    │                 │    │
│  │  └─────────────┘  └─────────────┘  └─────────────┘                 │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                      │                                      │
│                                      ▼                                      │
│                          ┌─────────────────────┐                            │
│                          │     report.pdf      │                            │
│                          └─────────────────────┘                            │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Directory Structure

Follows a strict taxonomy to prevent "catch-all" folders:

| Folder | Purpose | Anti-pattern avoided |
|--------|---------|---------------------|
| `handlers/` | Use-cases, business flow, orchestration | Replaces vague "Service" |
| `queries/` | Data retrieval, no business rules | Separates read from write |
| `validators/` | Validation logic | Clear single responsibility |
| `transformers/` | Data shape conversion | Replaces "Helper" utilities |
| `factories/` | Object construction | Replaces "Builder" folder |
| `dto/` | Typed data transfer objects | Replaces array-passing |
| `clients/` | External integrations | Clear boundary |
| `adapters/` | Map external responses to internal DTOs | Isolates external formats |
| `commands/` | Console controllers (Yii2 convention) | — |
| `jobs/` | Queue payloads | — |

**Dropped types**: `services/` (catch-all), `helpers/` (hidden coupling), `components/` (Yii2 framework location, not architecture type).

```
yii/
├── config/
│   ├── console.php                     # Yii console config
│   ├── params.php                      # Application parameters
│   ├── container.php                   # DI container definitions
│   │
│   ├── industries/                     # Industry configurations
│   │   ├── integrated_oil_gas.json
│   │   └── [industry_id].json
│   │
│   └── schemas/                        # JSON Schema definitions
│       ├── industry-config.schema.json
│       ├── industry-datapack.schema.json
│       └── report-dto.schema.json
│
├── commands/                           # Console controllers (Yii2 convention)
│   ├── CollectController.php           # Phase 1: yii collect/industry
│   ├── AnalyzeController.php           # Phase 2: yii analyze/report
│   ├── RenderController.php            # Phase 3: yii render/pdf
│   └── PipelineController.php          # Full pipeline: yii pipeline/run
│
├── handlers/                           # Business flow & orchestration
│   ├── collection/                     # Phase 1: Data gathering
│   │   ├── CollectIndustryHandler.php  # Orchestrates full collection
│   │   ├── CollectCompanyHandler.php   # Single company collection
│   │   └── CollectMacroHandler.php     # Macro data collection
│   │
│   ├── analysis/                       # Phase 2: Analysis
│   │   ├── AnalyzeReportHandler.php    # Orchestrates analysis
│   │   ├── CalculateGapsHandler.php    # Valuation gap calculations
│   │   └── DetermineRatingHandler.php  # BUY/HOLD/SELL logic
│   │
│   └── rendering/                      # Phase 3: PDF generation
│       └── RenderPdfHandler.php        # Calls Python subprocess
│
├── queries/                            # Data retrieval (no business rules)
│   ├── IndustryConfigQuery.php         # Load/validate industry configs
│   ├── DataPackQuery.php               # Load existing datapacks
│   └── ReportDtoQuery.php              # Load existing report DTOs
│
├── validators/                         # Validation logic
│   ├── SchemaValidator.php             # JSON Schema validation
│   ├── CollectionGateValidator.php     # Gate after Phase 1
│   └── AnalysisGateValidator.php       # Gate after Phase 2
│
├── transformers/                       # Data shape conversion
│   ├── DataPackTransformer.php         # Build DataPack from collected data
│   ├── ReportDtoTransformer.php        # Build ReportDTO from analysis
│   └── PeerAverageTransformer.php      # Calculate peer averages
│
├── factories/                          # Object construction
│   ├── DataPointFactory.php            # Create typed datapoints
│   ├── DataPackFactory.php             # Create IndustryDataPack
│   └── ReportDtoFactory.php            # Create ReportDTO
│
├── dto/                                # Typed data transfer objects
│   ├── IndustryDataPack.php            # Phase 1 output
│   ├── ReportDto.php                   # Phase 2 output
│   ├── CompanyData.php                 # Company within DataPack
│   ├── MacroData.php                   # Macro data within DataPack
│   ├── GateResult.php                  # Validation gate result
│   ├── ValidationResult.php            # Schema validation result
│   │
│   └── datapoints/                     # Typed datapoint value objects
│       ├── DataPointNumber.php
│       ├── DataPointMoney.php
│       ├── DataPointPercent.php
│       ├── DataPointRatio.php
│       ├── DataPointUrl.php
│       ├── FiscalYearMoney.php
│       └── SourceLocator.php
│
├── clients/                            # External integrations
│   ├── WebSearchClient.php             # Web search abstraction
│   ├── WebFetchClient.php              # Web page fetching
│   └── PythonRendererClient.php        # Python subprocess wrapper
│
├── adapters/                           # Map external responses → internal DTOs
│   ├── YahooFinanceAdapter.php         # Parse Yahoo Finance pages
│   ├── ReutersAdapter.php              # Parse Reuters pages
│   └── SearchResultAdapter.php         # Parse search results
│
├── enums/                              # Enumerated types
│   ├── Rating.php                      # BUY, HOLD, SELL
│   ├── Fundamentals.php                # Improving, Mixed, Deteriorating
│   ├── Risk.php                        # Acceptable, Elevated, Unacceptable
│   ├── CollectionMethod.php            # web_search, web_fetch, api, etc.
│   ├── CollectionStatus.php            # complete, partial, failed
│   └── Severity.php                    # required, recommended, optional
│
├── exceptions/                         # Custom exceptions
│   ├── CollectionException.php
│   ├── ValidationException.php
│   ├── GateFailedException.php
│   └── RenderException.php
│
├── jobs/                               # Queue payloads (optional)
│   ├── CollectIndustryJob.php
│   ├── AnalyzeReportJob.php
│   └── RenderPdfJob.php
│
├── models/                             # Yii2 ActiveRecord models (if needed)
│   └── .gitkeep                        # Placeholder - may not need AR models
│
├── runtime/                            # Generated files (gitignored)
│   └── datapacks/
│       └── [industry_id]/
│           └── [datapack_uuid]/
│               ├── datapack.json
│               ├── validation.json
│               ├── report-dto.json
│               └── report.pdf
│
└── tests/
    ├── unit/
    │   ├── handlers/
    │   │   ├── CalculateGapsHandlerTest.php
    │   │   └── DetermineRatingHandlerTest.php
    │   ├── validators/
    │   │   └── SchemaValidatorTest.php
    │   ├── transformers/
    │   │   └── PeerAverageTransformerTest.php
    │   └── dto/
    │       └── DataPointMoneyTest.php
    │
    ├── integration/
    │   ├── CollectionGateTest.php
    │   └── AnalysisGateTest.php
    │
    └── fixtures/
        ├── valid-datapack.json
        └── valid-report-dto.json

python-renderer/                        # Python PDF generation
├── requirements.txt
├── render_pdf.py                       # Main entry point
├── charts.py                           # Chart generation
├── layout.py                           # Page layout
└── styles.py                           # Visual styles
```

### Folder Decision Guide

| Question | Answer | Folder |
|----------|--------|--------|
| Does it orchestrate a flow or make business decisions? | Yes | `handlers/` |
| Does it only retrieve data without business logic? | Yes | `queries/` |
| Does it validate data? | Yes | `validators/` |
| Does it convert data from one shape to another? | Yes | `transformers/` |
| Does it construct complex objects? | Yes | `factories/` |
| Is it a typed data structure passed between layers? | Yes | `dto/` |
| Does it call an external system? | Yes | `clients/` |
| Does it map external response format to internal DTO? | Yes | `adapters/` |
| Is it a console entry point? | Yes | `commands/` |
| Is it a queue payload? | Yes | `jobs/` |

### When to Create a Module

When a domain area has:
- More than 3-4 handlers serving the same subject
- Its own set of Query/Validator/Transformer
- Need for clear team ownership

Then extract to a Yii2 module:

```
modules/
└── equityresearch/
    ├── Module.php
    ├── commands/
    ├── handlers/
    ├── queries/
    ├── validators/
    ├── transformers/
    ├── dto/
    └── ...
```

For now, the flat structure is appropriate given the project scope.

---

## Data Flow

### Phase 1: Collection

```
INPUT                           PROCESS                         OUTPUT
─────                           ───────                         ──────
industry config      ──►   CollectIndustryHandler       ──►   IndustryDataPack
(JSON file)                      │                              (JSON file)
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
            CollectMacroHandler       CollectCompanyHandler (×N)
                    │                         │
                    │              ┌──────────┼──────────┐
                    │              ▼          ▼          ▼
                    │      Valuation   Financials   Quarter
                    │      Metrics     History     Metadata
                    │              │          │          │
                    └──────────────┴──────────┴──────────┘
                                      │
                                      ▼
                              IndustryDataPack
                                      │
                                      ▼
                           CollectionGateValidator
                                      │
                              PASS or FAIL
```

### Phase 2: Analysis

```
INPUT                           PROCESS                         OUTPUT
─────                           ───────                         ──────
IndustryDataPack     ──►   AnalyzeReportHandler         ──►   ReportDTO
focal_ticker                     │                              (JSON file)
peer_tickers[]                   │
                    ┌────────────┼────────────┐
                    ▼            ▼            ▼
          CalculateGaps   DetermineRating   ReportDto
             Handler         Handler        Transformer
                    │            │            │
                    └────────────┴────────────┘
                                 │
                                 ▼
                             ReportDTO
                                 │
                                 ▼
                       AnalysisGateValidator
                                 │
                         PASS or FAIL
```

### Phase 3: Rendering

```
INPUT                           PROCESS                         OUTPUT
─────                           ───────                         ──────
ReportDTO            ──►   RenderPdfHandler             ──►   report.pdf
(JSON file)                      │
                                 │
                    ┌────────────┴────────────┐
                    ▼                         ▼
            PythonRenderer           Python subprocess
               Client                        │
                              ┌──────────────┼──────────────┐
                              ▼              ▼              ▼
                          ReportLab     matplotlib      layout
                              │              │              │
                              └──────────────┴──────────────┘
                                             │
                                             ▼
                                        report.pdf
```

---

## Key Concepts

### Datapoint Provenance

Every collected value must include:

```json
{
  "value": 12.5,
  "unit": "ratio",
  "as_of": "2025-12-10",
  "source_url": "https://finance.yahoo.com/quote/SHEL",
  "retrieved_at": "2025-12-13T10:30:00Z",
  "method": "web_fetch",
  "source_locator": {
    "type": "html",
    "selector": "td[data-test='FORWARD_PE-value']",
    "snippet": "Forward P/E: 12.5"
  }
}
```

### Typed Datapoints

| Type | Use Case | Key Fields |
|------|----------|------------|
| `DataPointNumber` | Generic numbers (production volumes) | `value`, `unit` |
| `DataPointMoney` | Monetary values | `value`, `currency`, `scale`, `fx_conversion` |
| `DataPointPercent` | Percentages (yields, margins) | `value` (stored as 4.5 for 4.5%) |
| `DataPointRatio` | Dimensionless ratios (P/E, EV/EBITDA) | `value` (stored as 12.5 for 12.5x) |
| `DataPointUrl` | URLs to documents | `value`, `verified_accessible` |

### Nullable vs Required

- **Required datapoint**: Must have a value. Collection fails if missing.
- **Nullable datapoint**: May have `null` value, but must record `attempted_sources`.

```json
{
  "value": null,
  "as_of": "2025-12-13",
  "retrieved_at": "2025-12-13T10:30:00Z",
  "method": "not_found",
  "attempted_sources": [
    "https://finance.yahoo.com/quote/SHEL",
    "https://www.reuters.com/companies/SHEL.L"
  ]
}
```

### Validation Gates

Gates are checkpoints that prevent bad data from flowing downstream.

**Collection Gate (after Phase 1):**
- JSON Schema compliance
- All required datapoints present
- All configured companies collected
- Macro data within freshness threshold (10 days)
- Minimum financial history present

**Analysis Gate (after Phase 2):**
- JSON Schema compliance
- Recompute peer averages → must match reported values
- Recompute valuation gap → must match reported value
- Rating rule path consistency
- Temporal sanity (no past catalysts marked "upcoming")

### Rating Logic

```
IF fundamentals == "Deteriorating":
    rating = SELL, rule_path = "SELL_FUNDAMENTALS"

ELSE IF risk == "Unacceptable":
    rating = SELL, rule_path = "SELL_RISK"

ELSE IF valuation_gap == null:
    rating = HOLD, rule_path = "HOLD_INSUFFICIENT_DATA"

ELSE IF valuation_gap > 15% AND fundamentals == "Improving" AND risk == "Acceptable":
    rating = BUY, rule_path = "BUY_ALL_CONDITIONS"

ELSE:
    rating = HOLD, rule_path = "HOLD_DEFAULT"
```

### Valuation Gap Calculation

```
For each metric (fwd_pe, ev_ebitda, fcf_yield, div_yield):
    IF focal_value AND peer_avg both non-null:
        For P/E, EV/EBITDA (lower is better):
            gap = ((peer_avg - focal) / peer_avg) × 100
        For yields (higher is better):
            gap = ((focal - peer_avg) / peer_avg) × 100

IF gaps.count >= 2:
    composite_gap = average(gaps)
ELSE:
    composite_gap = null
```

---

## CLI Commands

### Phase 1: Collect

```bash
# List available industries
yii collect/list

# Collect data for an industry
yii collect/industry integrated_oil_gas

# Output:
# runtime/datapacks/integrated_oil_gas/{uuid}/datapack.json
# runtime/datapacks/integrated_oil_gas/{uuid}/validation.json
```

### Phase 2: Analyze

```bash
# Generate report DTO
yii analyze/report \
    --datapack=runtime/datapacks/integrated_oil_gas/{uuid}/datapack.json \
    --focal=SHEL \
    --peers=BP,XOM,CVX,TTE

# Output:
# runtime/datapacks/integrated_oil_gas/{uuid}/report-dto.json
```

### Phase 3: Render

```bash
# Render PDF
yii render/pdf \
    --dto=runtime/datapacks/integrated_oil_gas/{uuid}/report-dto.json

# Output:
# runtime/datapacks/integrated_oil_gas/{uuid}/report.pdf
```

### Full Pipeline

```bash
# Run all three phases
yii pipeline/run integrated_oil_gas --focal=SHEL --peers=BP,XOM,CVX,TTE
```

---

## Configuration Files

### Industry Config (`config/industries/integrated_oil_gas.json`)

Defines which companies to collect and industry-specific data requirements.

```json
{
  "id": "integrated_oil_gas",
  "name": "Integrated Oil & Gas",
  "sector": "Energy",
  "companies": [
    {
      "ticker": "SHEL",
      "name": "Shell plc",
      "listing_exchange": "NYSE",
      "listing_currency": "USD",
      "reporting_currency": "USD",
      "fy_end_month": 12
    }
  ],
  "macro_requirements": {
    "commodity_benchmark": "BRENT",
    "margin_proxy": "CRACK_3_2_1",
    "sector_index": "XLE",
    "required_indicators": [],
    "optional_indicators": []
  },
  "data_requirements": {
    "history_years": 5,
    "quarters_to_fetch": 4,
    "valuation_metrics": [
      { "key": "market_cap", "unit": "currency", "required": true },
      { "key": "fwd_pe", "unit": "ratio", "required": true },
      { "key": "ev_ebitda", "unit": "ratio", "required": true },
      { "key": "trailing_pe", "unit": "ratio", "required": false },
      { "key": "fcf_yield", "unit": "percent", "required": false },
      { "key": "div_yield", "unit": "percent", "required": false },
      { "key": "net_debt_ebitda", "unit": "ratio", "required": false },
      { "key": "price_to_book", "unit": "ratio", "required": false }
    ],
    "annual_financial_metrics": [
      { "key": "revenue", "unit": "currency", "required": false },
      { "key": "ebitda", "unit": "currency", "required": false },
      { "key": "net_income", "unit": "currency", "required": false },
      { "key": "net_debt", "unit": "currency", "required": false },
      { "key": "free_cash_flow", "unit": "currency", "required": false }
    ],
    "quarter_metrics": [
      { "key": "revenue", "unit": "currency", "required": false },
      { "key": "ebitda", "unit": "currency", "required": false },
      { "key": "net_income", "unit": "currency", "required": false },
      { "key": "free_cash_flow", "unit": "currency", "required": false }
    ],
    "operational_metrics": []
  }
}
```

### Application Parameters (`config/params.php`)

```php
return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'pythonRendererPath' => '@app/python-renderer',
    'pythonBinary' => '/usr/bin/python3',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
];
```

---

## Dependencies

### PHP (composer.json)

```json
{
  "require": {
    "php": ">=8.5",
    "yiisoft/yii2": "~2.0.49",
    "opis/json-schema": "^2.3",
    "symfony/process": "^6.4",
    "yiisoft/yii2-queue": "^2.3",
    "ramsey/uuid": "^4.7"
  },
  "require-dev": {
    "codeception/codeception": "^5.0",
    "codeception/module-asserts": "^3.0"
  }
}
```

### Python (python-renderer/requirements.txt)

```
reportlab>=4.0
matplotlib>=3.8
pillow>=10.0
```

---

## Error Handling

### Gate Failures

Gates return `GateResult`:

```php
class GateResult
{
    public function __construct(
        public bool $passed,
        public array $errors,    // Fatal issues
        public array $warnings,  // Non-fatal issues
    ) {}
}
```

- **Errors**: Stop the pipeline. Must be fixed before proceeding.
- **Warnings**: Log and continue. Review recommended.

### Exit Codes

| Code | Constant | Meaning |
|------|----------|---------|
| 0 | `ExitCode::OK` | Success |
| 65 | `ExitCode::DATAERR` | Data/validation error (gate failed) |
| 70 | `ExitCode::SOFTWARE` | Internal error (exception) |

---

## Testing Strategy

### Unit Tests

- `CalculateGapsHandler`: Test gap calculation with various input combinations
- `DetermineRatingHandler`: Test all rating rule paths
- `SchemaValidator`: Test schema loading and validation
- `DataPointMoney`: Test FX conversion logic

### Integration Tests

- `CollectionGateTest`: Validate gate passes/fails correctly
- `AnalysisGateTest`: Validate recomputation matches

### Fixtures

- `valid-datapack.json`: Known-good IndustryDataPack
- `valid-report-dto.json`: Known-good ReportDTO

---

## Development Workflow

### Adding a New Industry

1. Create `config/industries/{industry_id}.json`
2. Define companies, macro requirements, data requirements
3. Run `yii collect/industry {industry_id}`
4. Verify datapack.json and validation.json

### Adding a New Valuation Metric

1. Add to `industry-datapack.schema.json` under `companyData.valuation`
2. Add to `report-dto.schema.json` under `peer_valuation`
3. Update `CalculateGapsHandler` if metric affects valuation gap
4. Update `CollectionGateValidator` if metric is required
5. Update `AnalysisGateValidator` to recompute if needed

### Adding a New Chart Type

1. Add data structure to `report-dto.schema.json`
2. Implement in `python-renderer/charts.py`
3. Integrate into `python-renderer/layout.py`

---

## Glossary

| Term | Definition |
|------|------------|
| **Focal company** | The company being analyzed (subject of the report) |
| **Peers** | Comparison companies in the same industry |
| **DataPack** | Collected raw data for an industry (Phase 1 output) |
| **ReportDTO** | Analyzed data ready for rendering (Phase 2 output) |
| **Gate** | Validation checkpoint between phases |
| **Valuation gap** | Percentage difference between focal and peer average valuations |
| **Provenance** | Source attribution for a datapoint (URL, timestamp, method) |
| **LTM** | Last Twelve Months (trailing financial metric) |
| **FY** | Fiscal Year |

---

## Next Steps

### Phase 0: Project Setup
- [ ] Set up project skeleton (composer.json, directories, yii entry point)
- [ ] Configure Yii2 console application (config/console.php, params.php, container.php)
- [ ] Implement enums (Rating, Fundamentals, Risk, CollectionMethod, etc.)
- [ ] Implement exceptions (CollectionException, ValidationException, etc.)

### Phase 1: Data Collection Infrastructure
- [ ] Implement DTO datapoints (DataPointMoney, DataPointRatio, etc.)
- [ ] Implement DTO structures (IndustryDataPack, CompanyData, MacroData)
- [ ] Implement DataPointFactory
- [ ] Implement SchemaValidator
- [ ] Implement queries (IndustryConfigQuery, DataPackQuery)
- [ ] Implement clients (WebSearchClient, WebFetchClient)
- [ ] Implement adapters (YahooFinanceAdapter, SearchResultAdapter)

### Phase 1: Data Collection Logic
- [ ] Implement CollectMacroHandler
- [ ] Implement CollectCompanyHandler
- [ ] Implement CollectIndustryHandler (orchestrates the above)
- [ ] Implement DataPackTransformer
- [ ] Implement CollectionGateValidator
- [ ] Implement CollectController
- [ ] Test Phase 1 end-to-end

### Phase 2: Analysis
- [ ] Implement PeerAverageTransformer
- [ ] Implement CalculateGapsHandler
- [ ] Implement DetermineRatingHandler
- [ ] Implement ReportDtoTransformer
- [ ] Implement ReportDtoFactory
- [ ] Implement AnalyzeReportHandler (orchestrates the above)
- [ ] Implement AnalysisGateValidator
- [ ] Implement AnalyzeController
- [ ] Test Phase 2 end-to-end

### Phase 3: Rendering
- [ ] Implement Python renderer (render_pdf.py, charts.py, layout.py)
- [ ] Implement PythonRendererClient
- [ ] Implement RenderPdfHandler
- [ ] Implement RenderController
- [ ] Test Phase 3 end-to-end

### Phase 4: Integration
- [ ] Implement PipelineController (full run)
- [ ] Implement queue jobs (optional: CollectIndustryJob, etc.)
- [ ] End-to-end integration tests
- [ ] Documentation review
