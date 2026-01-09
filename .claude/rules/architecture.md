# Architecture & Folder Taxonomy

## General Principles

- Use dependency injection: `Yii::$container->get(ClassName::class)`
- Keep controllers thin; delegate to handlers
- Move complex queries to dedicated Query classes

## Folder Structure

Use specific folders, not catch-alls:

| Folder | Purpose | Anti-pattern |
|--------|---------|--------------|
| `handlers/` | Business flow, orchestration | ~~services/~~ |
| `queries/` | Data retrieval, query classes | inline queries |
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

- `queries/` — Domain query classes (CompanyQuery, IndustryQuery)

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

### Naming Conventions

| Pattern | Use When | Example |
|---------|----------|---------|
| `active()` | Filter by status | `->active()` |
| `withX($x)` | Filter by relation/value | `->withIndustry($id)` |
| `hasX()` | Filter for non-null | `->hasTicker()` |
| `inX()` | Filter by set membership | `->inSector('energy')` |
| `alphabetical()` | Common sort order | `->alphabetical()` |
| `orderedByX()` | Specific sort | `->orderedByMarketCap()` |

### Usage

```php
// Good: chainable, readable
$companies = Company::find()
    ->active()
    ->withIndustry($industryId)
    ->hasTicker()
    ->orderedByMarketCap()
    ->all();

// Bad: inline conditions
$companies = Company::find()
    ->andWhere(['status' => 'active'])
    ->andWhere(['industry_id' => $industryId])
    ->andWhere(['not', ['ticker' => null]])
    ->orderBy(['market_cap' => SORT_DESC])
    ->all();
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
