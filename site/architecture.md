# Architecture

AIMM uses a handler-based architecture with strict folder taxonomy to prevent "catch-all" folders.

## Why Handlers (not Services)

This project avoids a generic `*Service` layer because it tends to become a catch-all bucket.

Instead:
- **Commands** orchestrate and validate input (CLI)
- **Handlers** perform one concrete application action end-to-end
- **Adapters/Clients** talk to external systems
- **Schemas/DTOs** define stable contracts between phases

::: tip Naming Rule
Prefer specific, action-oriented names like `IndustryCollectionHandler` and `PdfRenderHandler` over broad `DataService`/`CollectionService` style names.
:::

## Layer Responsibilities

| Layer | Purpose | Example |
|-------|---------|---------|
| Commands | Console entry points, input validation | `CollectController` |
| Handlers | Business flow, orchestration | `CollectIndustryHandler` |
| Queries | Data retrieval, no business rules | `IndustryConfigQuery` |
| Validators | Validation logic | `CollectionGateValidator` |
| Transformers | Data shape conversion | `DataPackTransformer` |
| Factories | Object construction | `DataPointFactory` |
| DTOs | Typed data transfer objects | `IndustryDataPack` |
| Clients | External integrations | `WebFetchClient` |
| Adapters | External → internal mapping | `YahooFinanceAdapter` |

## Folder Decision Guide

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

## Dropped Folder Types

These folder types are explicitly banned:

| Folder | Reason |
|--------|--------|
| `services/` | Catch-all bucket, use `handlers/` instead |
| `helpers/` | Hidden coupling, use `transformers/` instead |
| `components/` | Yii2 framework location, not architecture type |
| `utils/` | Catch-all, be specific |

## When to Create a Module

Extract to a Yii2 module when a domain area has:

- More than 3-4 handlers serving the same subject
- Its own set of Query/Validator/Transformer
- Need for clear team ownership

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
