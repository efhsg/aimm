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

| Pattern | Why | Use Instead |
|---------|-----|-------------|
| `services/` folder | Too generic | Specific handler/factory/validator |
| `helpers/` folder | Catch-all | transformers/, query methods |
| `utils/` folder | Meaningless | Specific purpose folder |
| `misc/` folder | Undefined | Named folder |
| `components/` folder | Yii framework only | Proper namespaced classes |
| Raw SQL Query classes | Bypasses ORM | `ModelNameQuery extends ActiveQuery` |
| Tables without models | No type safety | Create `ModelName extends ActiveRecord` |

**Exception:** Raw SQL is acceptable for read-only reporting queries with complex aggregations (e.g., `IndustryListQuery` with GROUP BY and multiple JOINs) when documented.

## ActiveRecord Models

**Every database table must have a corresponding ActiveRecord model.**

### Rules

1. **One model per table** — No table without a model, no raw SQL for CRUD operations
2. **Naming:** `ModelName` matches table name in PascalCase (e.g., `company` → `Company`)
3. **Location:** `models/` — All ActiveRecord models
4. **Final classes:** Use `final class` to prevent unintended inheritance

### Required Structure

```php
<?php

declare(strict_types=1);

namespace app\models;

use app\queries\CompanyQuery;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the company table.
 *
 * @property int $id
 * @property string $ticker
 * @property int|null $industry_id
 *
 * @property-read Industry|null $industry
 */
final class Company extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%company}}';
    }

    public function rules(): array
    {
        return [
            [['ticker'], 'required'],
            [['ticker'], 'string', 'max' => 20],
            [['ticker'], 'unique'],
            [['industry_id'], 'integer'],
        ];
    }

    public function getIndustry(): ActiveQuery
    {
        return $this->hasOne(Industry::class, ['id' => 'industry_id']);
    }

    public static function find(): CompanyQuery
    {
        return new CompanyQuery(static::class);
    }
}
```

### Checklist

- [ ] `declare(strict_types=1)` at top
- [ ] `final class` declaration
- [ ] PHPDoc `@property` for all columns
- [ ] PHPDoc `@property-read` for all relations
- [ ] `tableName()` with `{{%}}` prefix
- [ ] `rules()` with validation
- [ ] `find()` returns typed Query class
- [ ] Relations as `getRelationName(): ActiveQuery`

## Query Classes

**Every ActiveRecord model must have a corresponding ActiveQuery class.**

### Rules

1. **Naming pattern:** `ModelName` + `ModelNameQuery` (e.g., `Company` + `CompanyQuery`)
2. **Extends ActiveQuery:** Never use raw SQL via `Connection` for domain entities
3. **Location:** `queries/` — All ActiveQuery classes
4. **Chainable methods:** All filter methods return `self`
5. **Wired via Model:** Access through `Model::find()`, not DI container

### Required Structure

```php
<?php

declare(strict_types=1);

namespace app\queries;

use app\models\Company;
use yii\db\ActiveQuery;

/**
 * ActiveQuery for {@see Company}.
 *
 * @extends ActiveQuery<Company>
 * @method Company[] all($db = null)
 * @method Company|null one($db = null)
 */
final class CompanyQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['status' => Company::STATUS_ACTIVE]);
    }

    public function inIndustry(int $industryId): self
    {
        return $this->andWhere(['industry_id' => $industryId]);
    }

    public function hasTicker(): self
    {
        return $this->andWhere(['not', ['ticker' => null]]);
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
