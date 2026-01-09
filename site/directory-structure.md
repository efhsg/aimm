# Directory Structure

AIMM follows a strict folder taxonomy to prevent "catch-all" folders.

## Top-Level Structure

```
aimm/
├── yii/                        # Yii2 application
│   ├── config/                 # Configuration files
│   │   ├── console.php
│   │   ├── params.php
│   │   ├── container.php
│   │   ├── industries/         # Industry configs
│   │   └── schemas/            # JSON Schema files
│   │
│   ├── src/                    # Application source
│   │   ├── commands/           # Console controllers
│   │   ├── handlers/           # Business logic
│   │   ├── queries/            # Data retrieval
│   │   ├── validators/         # Validation logic
│   │   ├── transformers/       # Data conversion
│   │   ├── factories/          # Object construction
│   │   ├── dto/                # Data transfer objects
│   │   ├── clients/            # External integrations
│   │   ├── adapters/           # External → internal mapping
│   │   ├── enums/              # Enumerated types
│   │   ├── exceptions/         # Custom exceptions
│   │   └── jobs/               # Queue payloads
│   │
│   ├── runtime/                # Generated files
│   │   └── datapacks/          # Pipeline outputs
│   │
│   └── tests/                  # Test suite
│       ├── unit/
│       ├── integration/
│       └── fixtures/
│
├── docs/                       # Internal documentation
│   ├── design/
│   ├── rules/
│   └── skills/
│
└── site/                       # VitePress docs (this site)
```

## Folder Purposes

### Commands (`commands/`)

Console controllers (Yii2 convention). Entry points for CLI operations.

```
commands/
├── CollectController.php       # yii collect/*
├── AnalyzeController.php       # yii analyze/*
├── RenderController.php        # yii render/*
└── PipelineController.php      # yii pipeline/*
```

### Handlers (`handlers/`)

Business flow and orchestration. Each handler performs one concrete application action.

```
handlers/
├── collection/
│   ├── CollectIndustryHandler.php
│   ├── CollectCompanyHandler.php
│   └── CollectMacroHandler.php
├── analysis/
│   ├── AnalyzeReportHandler.php
│   ├── CalculateGapsHandler.php
│   └── DetermineRatingHandler.php
└── rendering/
    └── RenderPdfHandler.php
```

### Queries (`queries/`)

Data retrieval without business rules. Read-only operations.

```
queries/
├── IndustryConfigQuery.php
├── DataPackQuery.php
└── ReportDtoQuery.php
```

### Validators (`validators/`)

Validation logic including gate validators.

```
validators/
├── SchemaValidator.php
├── CollectionGateValidator.php
└── AnalysisGateValidator.php
```

### Transformers (`transformers/`)

Data shape conversion. Replaces "helper" utilities.

```
transformers/
├── DataPackTransformer.php
├── ReportDtoTransformer.php
└── PeerAverageTransformer.php
```

### Factories (`factories/`)

Object construction for complex DTOs.

```
factories/
├── DataPointFactory.php
├── DataPackFactory.php
└── ReportDtoFactory.php
```

### DTOs (`dto/`)

Typed data transfer objects passed between layers.

```
dto/
├── IndustryDataPack.php
├── ReportDto.php
├── CompanyData.php
├── MacroData.php
├── GateResult.php
└── datapoints/
    ├── DataPointNumber.php
    ├── DataPointMoney.php
    ├── DataPointPercent.php
    ├── DataPointRatio.php
    └── DataPointUrl.php
```

### Clients (`clients/`)

External integrations. Clear boundary with external systems.

```
clients/
├── WebSearchClient.php
├── WebFetchClient.php
└── GotenbergClient.php
```

### Adapters (`adapters/`)

Map external responses to internal DTOs. Isolates external formats.

```
adapters/
├── YahooFinanceAdapter.php
├── ReutersAdapter.php
└── SearchResultAdapter.php
```

### Enums (`enums/`)

Enumerated types for type-safe values.

```
enums/
├── Rating.php              # BUY, HOLD, SELL
├── Fundamentals.php        # Improving, Mixed, Deteriorating
├── Risk.php                # Acceptable, Elevated, Unacceptable
├── CollectionMethod.php    # web_search, web_fetch, api
└── CollectionStatus.php    # complete, partial, failed
```

### Exceptions (`exceptions/`)

Custom exception classes.

```
exceptions/
├── CollectionException.php
├── ValidationException.php
├── GateFailedException.php
└── RenderException.php
```

## Dropped Folder Types

These folders are explicitly **banned**:

| Folder | Reason | Use Instead |
|--------|--------|-------------|
| `services/` | Catch-all bucket | `handlers/` |
| `helpers/` | Hidden coupling | `transformers/` |
| `components/` | Yii2 framework location | Appropriate layer |
| `utils/` | Catch-all | Be specific |
| `misc/` | Catch-all | Be specific |
