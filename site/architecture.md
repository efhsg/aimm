# Architecture & Folder Taxonomy

## General Principles

- Use dependency injection: `Yii::$container->get(ClassName::class)`
- Keep controllers thin; delegate to handlers
- Move complex queries to dedicated Query classes or Repositories
- **Strict Typing:** Use DTOs for all data passing between layers

## Folder Structure

Use specific folders, not catch-alls:

| Folder | Purpose | Anti-pattern |
|--------|---------|--------------|
| `handlers/` | Business flow, orchestration | ~~services/~~ |
| `queries/` | Data retrieval, repository implementations | inline queries |
| `validators/` | Validation logic | — |
| `transformers/` | Data shape conversion | ~~helpers/~~ |
| `factories/` | Object construction | ~~builders/~~ |
| `dto/` | Typed data transfer objects | ~~arrays~~ |
| `clients/` | Web fetch, rate limiting | — |
| `adapters/` | External API → internal mapping | — |
| `enums/` | Enumerated types | magic strings |
| `exceptions/` | Custom exceptions | — |
| `alerts/` | Notification dispatching | — |
| `events/` | Domain events | — |
| `jobs/` | Queue payloads | — |

## Yii2 Framework Folders

Keep as-is (standard Yii2 structure):

| Folder | Purpose |
|--------|---------|
| `commands/` | Console commands (CLI entry points) |
| `controllers/` | HTTP controllers (thin, delegate to handlers) |
| `models/` | ActiveRecord models |
| `views/` | View templates |
| `filters/` | Action filters (auth, rate limiting) |
| `log/` | Custom log targets |

## Banned for New Code

| Folder | Why | Use Instead |
|--------|-----|-------------|
| `services/` | Too generic | Specific handler/factory/validator |
| `helpers/` | Catch-all | transformers/, query methods |
| `utils/` | Meaningless | Specific purpose folder |
| `misc/` | Undefined | Named folder |
| `components/` | Yii framework only | Proper namespaced classes |

## Query Classes

Use query class methods instead of inline `->andWhere()`. Add new methods to Query classes when needed.

### Location

- `queries/` — Domain query classes (CompanyQuery, IndustryQuery) and Repositories

### Structure

```php
<?php

declare(strict_types=1);

namespace app\queries;

use app\models\Company;
use yii\db\ActiveQuery;

class CompanyQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['status' => Company::STATUS_ACTIVE]);
    }

    public function withIndustry(int $industryId): self
    {
        return $this->andWhere(['industry_id' => $industryId]);
    }

    public function hasTicker(): self
    {
        return $this->andWhere(['not', ['ticker' => null]]);
    }

    // Override for type hints
    public function all($db = null): array
    {
        return parent::all($db);
    }

    public function one($db = null): Company|array|null
    {
        return parent::one($db);
    }
}
```

## DTOs (Data Transfer Objects)

Immutable objects for structured data transfer between layers.

### Location

- `dto/` — All data transfer objects
- Subdirectories by domain: `dto/analysis/`, `dto/industry/`, etc.

### Structure

```php
<?php

declare(strict_types=1);

namespace app\dto;

final readonly class CompanyData
{
    public function __construct(
        public string $ticker,
        public string $name,
        public ?string $source = null,
    ) {}
}
```

### Conventions

- Use `readonly` classes with constructor property promotion
- Direct property access (no getters/setters needed)
- Full type coverage including union types
- DTOs can contain other DTOs (composition)

## Architecture

AIMM uses a handler-based architecture with strict folder taxonomy to prevent "catch-all" folders.

## Why Handlers (not Services)

This project avoids a generic `*Service` layer because it tends to become a catch-all bucket.

Instead:
- **Commands** orchestrate and validate input (CLI)
- **Handlers** perform one concrete application action end-to-end
- **Adapters/Clients** talk to external systems
- **Schemas/DTOs** define stable contracts between phases

::: tip Naming Rule
Prefer specific, action-oriented names like `IndustryCollectionHandler` and `PdfGenerationHandler` over broad `DataService`/`CollectionService` style names.
:::

## Layer Responsibilities

| Layer | Purpose | Example |
|-------|---------|---------|
| Commands | Console entry points, input validation | `CollectController` |
| Handlers | Business flow, orchestration | `CollectIndustryHandler` |
| Queries | Data retrieval, no business rules | `IndustryQuery` |
| Validators | Validation logic | `CollectionGateValidator` |
| Transformers | Data shape conversion | `PeerAverageTransformer` |
| Factories | Object construction | `DataPointFactory` |
| DTOs | Typed data transfer objects | `IndustryAnalysisContext` |
| Clients | External integrations | `GuzzleWebFetchClient` |
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
