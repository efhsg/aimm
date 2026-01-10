# Directory Structure

AIMM follows a strict folder taxonomy to prevent "catch-all" folders.

## Top-Level Structure

```
aimm/
├── yii/                        # Yii2 application
│   ├── config/                 # Configuration files
│   │   ├── console.php
│   │   ├── web.php
│   │   ├── params.php
│   │   ├── container.php
│   │   ├── industries/         # Legacy industry configs (unused)
│   │   └── schemas/            # JSON Schema files
│   │
│   ├── src/                    # Application source
│   │   ├── commands/           # Console controllers
│   │   ├── handlers/           # Business logic
│   │   ├── queries/            # Data retrieval & Repositories
│   │   ├── validators/         # Validation logic
│   │   ├── transformers/       # Data conversion
│   │   ├── factories/          # Object construction
│   │   ├── dto/                # Data transfer objects
│   │   ├── clients/            # External integrations
│   │   ├── adapters/           # External -> internal mapping
│   │   ├── enums/              # Enumerated types
│   │   ├── exceptions/         # Custom exceptions
│   │   ├── models/             # ActiveRecord models
│   │   └── jobs/               # Queue payloads
│   │
│   ├── runtime/                # Generated files
│   ├── migrations/             # DB schema migrations
│   └── tests/                  # Test suite
│
└── site/                       # VitePress docs (this site)
```

## Folder Examples

### Commands (`commands/`)

Console entry points:

```
commands/
├── CollectController.php
├── AnalyzeController.php
├── PdfController.php
├── SeedController.php
├── DbController.php
└── CollectPriceHistoryController.php
```

### Handlers (`handlers/`)

Business flows:

```
handlers/
├── collection/
├── analysis/
├── pdf/
├── industry/
└── collectionpolicy/
```

### Transformers (`transformers/`)

```
transformers/
├── PeerAverageTransformer.php
└── CurrencyConverter.php
```

### Factories (`factories/`)

```
factories/
├── DataPointFactory.php
├── CompanyDataDossierFactory.php
└── pdf/ReportDataFactory.php
```

### DTOs (`dto/`)

```
dto/
├── CompanyData.php
├── MacroData.php
├── GateResult.php
├── analysis/IndustryAnalysisContext.php
├── report/RankedReportDTO.php
└── datapoints/
```

### Clients (`clients/`)

```
clients/
├── GotenbergClient.php
├── GuzzleWebFetchClient.php
└── RateLimiterInterface.php
```

### Enums (`enums/`)

```
enums/
├── Rating.php
├── RatingRulePath.php
├── CollectionStatus.php
├── CollectionMethod.php
└── PdfJobStatus.php
```
