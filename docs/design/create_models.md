# Implementation Plan: Create ActiveRecord Models

**Purpose:** Step-by-step guide for implementing all missing ActiveRecord models.

## Prerequisites

- Read `/home/erwin/projects/aimm/.claude/rules/coding-standards.md`
- Read `/home/erwin/projects/aimm/.claude/rules/architecture.md`
- Reference implementation: `yii/src/models/CollectionAttempt.php` + `yii/src/queries/CollectionAttemptQuery.php`

## Conventions

All models follow these patterns (derived from existing codebase):

```php
<?php

declare(strict_types=1);

namespace app\models;

use app\queries\ModelNameQuery;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for the table_name table.
 *
 * @property int $id
 * @property string $field
 * ...
 *
 * @property-read RelatedModel $relation
 */
final class ModelName extends ActiveRecord
{
    // Constants for enum values
    public const STATUS_ACTIVE = 'active';

    public static function tableName(): string
    {
        return '{{%table_name}}';
    }

    public function rules(): array
    {
        return [
            // Required → Types → Enums → Defaults → Safe → Foreign keys
        ];
    }

    public function getRelation(): ActiveQuery
    {
        return $this->hasOne(RelatedModel::class, ['id' => 'relation_id']);
    }

    public static function find(): ModelNameQuery
    {
        return new ModelNameQuery(static::class);
    }

    // Domain methods (write operations)
}
```

Query classes in `yii/src/queries/`:

```php
<?php

declare(strict_types=1);

namespace app\queries;

use app\models\ModelName;
use yii\db\ActiveQuery;

/**
 * ActiveQuery for {@see ModelName}.
 *
 * @extends ActiveQuery<ModelName>
 * @method ModelName[] all($db = null)
 * @method ModelName|null one($db = null)
 */
final class ModelNameQuery extends ActiveQuery
{
    // Chainable filter methods returning self
}
```

---

## Phase 1: Foundation Models (No Dependencies)

These models have no foreign key dependencies on other models being created.

### 1.1 Sector

**Files:**
- Create: `yii/src/models/Sector.php`
- Refactor: `yii/src/queries/SectorQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
slug: string(50) NOT NULL UNIQUE
name: string(100) NOT NULL
created_at: datetime NOT NULL
```

**Model properties:**
```php
@property int $id
@property string $slug
@property string $name
@property string $created_at

@property-read Industry[] $industries
```

**Rules:**
```php
[['slug', 'name'], 'required'],
[['slug'], 'string', 'max' => 50],
[['slug'], 'unique'],
[['name'], 'string', 'max' => 100],
[['created_at'], 'safe'],
```

**Relations:**
```php
public function getIndustries(): ActiveQuery
{
    return $this->hasMany(Industry::class, ['sector_id' => 'id']);
}
```

**Query methods (READ → ActiveQuery):**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findById($id)` | Remove (use `Sector::findOne($id)`) |
| `findBySlug($slug)` | `bySlug(string $slug): self` |
| `findByName($name)` | `byName(string $name): self` |
| `findAll()` | `alphabetical(): self` |
| `findAllWithCounts()` | `withIndustryCounts(): self` (use `->select()`, `->leftJoin()`, `->groupBy()`) |

**Write methods (→ Model or delete):**
| Old Method | Action |
|------------|--------|
| `insert($data)` | Delete. Use `$sector->save()` |
| `update($id, $data)` | Delete. Use `$sector->save()` |
| `delete($id)` | Delete. Use `$sector->delete()` |

**Steps:**
1. Create `yii/src/models/Sector.php` with schema above
2. Refactor `yii/src/queries/SectorQuery.php` to extend `ActiveQuery`
3. Add `find()` method to Sector model
4. Update consumers to use `Sector::find()->...` pattern
5. Remove DI container entry for `SectorQuery`
6. Run tests

---

### 1.2 CollectionPolicy

**Files:**
- Create: `yii/src/models/CollectionPolicy.php`
- Refactor: `yii/src/queries/CollectionPolicyQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
slug: string(100) NOT NULL UNIQUE
name: string(100) NOT NULL
description: string(500) NULL
history_years: tinyint unsigned NOT NULL DEFAULT 5
quarters_to_fetch: tinyint unsigned NOT NULL DEFAULT 8
valuation_metrics: json NOT NULL
annual_financial_metrics: json NULL
quarterly_financial_metrics: json NULL
operational_metrics: json NULL
commodity_benchmark: string(50) NULL
margin_proxy: string(50) NULL
sector_index: string(50) NULL
required_indicators: json NULL
optional_indicators: json NULL
source_priorities: json NULL
analysis_thresholds: json NULL
created_by: string(100) NULL
updated_by: string(100) NULL
created_at: datetime NOT NULL
updated_at: datetime NOT NULL
```

**Model properties:**
```php
@property int $id
@property string $slug
@property string $name
@property string|null $description
@property int $history_years
@property int $quarters_to_fetch
@property array $valuation_metrics
@property array|null $annual_financial_metrics
@property array|null $quarterly_financial_metrics
@property array|null $operational_metrics
@property string|null $commodity_benchmark
@property string|null $margin_proxy
@property string|null $sector_index
@property array|null $required_indicators
@property array|null $optional_indicators
@property array|null $source_priorities
@property array|null $analysis_thresholds
@property string|null $created_by
@property string|null $updated_by
@property string $created_at
@property string $updated_at

@property-read Industry[] $industries
```

**JSON handling:** Override `afterFind()` and `beforeSave()` to decode/encode JSON fields, or use Yii's JSON validator.

**Query methods (READ → ActiveQuery):**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findById($id)` | Remove (use `CollectionPolicy::findOne($id)`) |
| `findBySlug($slug)` | `bySlug(string $slug): self` |
| `findAll()` | `alphabetical(): self` |
| `findAnalysisThresholds($id)` | Keep in model as `getAnalysisThresholds(): ?array` or use `->select(['analysis_thresholds'])->scalar()` |

**Write methods (→ delete):**
- `insert()`, `update()`, `delete()` → Use model `save()` / `delete()`

---

### 1.3 FxRate

**Files:**
- Create: `yii/src/models/FxRate.php`
- Refactor: `yii/src/queries/FxRateQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
base_currency: string(3) NOT NULL
quote_currency: string(3) NOT NULL
rate_date: date NOT NULL
rate: decimal(12,6) NOT NULL
source_adapter: string(50) NOT NULL DEFAULT 'ecb'
collected_at: datetime NOT NULL
created_at: datetime NOT NULL

UNIQUE: (base_currency, quote_currency, rate_date)
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findClosestRate($base, $quote, $date)` | `forCurrencyPair(string $base, string $quote): self` + `closestToDate(string $date): self` |
| `findRatesInRange($pairs, $start, $end)` | `inDateRange(string $start, string $end): self` + `forPairs(array $pairs): self` |

---

## Phase 2: Core Domain Models

These depend on Phase 1 models.

### 2.1 Industry

**Files:**
- Create: `yii/src/models/Industry.php`
- Refactor: `yii/src/queries/IndustryQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
sector_id: bigint unsigned NOT NULL FK→sector
slug: string(100) NOT NULL UNIQUE
name: string(255) NOT NULL
description: string(500) NULL
policy_id: bigint unsigned NULL FK→collection_policy
is_active: tinyint NOT NULL DEFAULT 1
created_by: string(100) NULL
updated_by: string(100) NULL
created_at: datetime NOT NULL
updated_at: datetime NOT NULL
```

**Model properties:**
```php
@property int $id
@property int $sector_id
@property string $slug
@property string $name
@property string|null $description
@property int|null $policy_id
@property bool $is_active
@property string|null $created_by
@property string|null $updated_by
@property string $created_at
@property string $updated_at

@property-read Sector $sector
@property-read CollectionPolicy|null $policy
@property-read Company[] $companies
```

**Relations:**
```php
public function getSector(): ActiveQuery
{
    return $this->hasOne(Sector::class, ['id' => 'sector_id']);
}

public function getPolicy(): ActiveQuery
{
    return $this->hasOne(CollectionPolicy::class, ['id' => 'policy_id']);
}

public function getCompanies(): ActiveQuery
{
    return $this->hasMany(Company::class, ['industry_id' => 'id']);
}
```

**Query methods (READ → ActiveQuery):**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findById($id)` | Remove. Use `Industry::find()->with('sector')->where(['id' => $id])->one()` |
| `findBySlug($slug)` | `bySlug(string $slug): self` |
| `findAllActive()` | `active(): self` |
| `findBySectorId($id, $activeOnly)` | `inSector(int $sectorId): self` |
| `findAllWithStats()` | `withCompanyCount(): self` (use select/leftJoin/groupBy) |

**Write methods (→ Model):**
| Old Method | New Model Method |
|------------|------------------|
| `insert($data)` | Use `$industry->save()` |
| `update($id, $data)` | Use `$industry->save()` |
| `delete($id)` | Use `$industry->delete()` |
| `deactivate($id)` | `public function deactivate(): bool` |
| `activate($id)` | `public function activate(): bool` |
| `assignPolicy($id, $policyId)` | `public function assignPolicy(?int $policyId): bool` |

**Model methods:**
```php
public function deactivate(): bool
{
    $this->is_active = false;
    return $this->save(false, ['is_active']);
}

public function activate(): bool
{
    $this->is_active = true;
    return $this->save(false, ['is_active']);
}

public function assignPolicy(?int $policyId): bool
{
    $this->policy_id = $policyId;
    return $this->save(false, ['policy_id']);
}
```

---

### 2.2 Company

**Files:**
- Create: `yii/src/models/Company.php`
- Refactor: `yii/src/queries/CompanyQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
industry_id: bigint unsigned NULL FK→industry
ticker: string(20) NOT NULL UNIQUE
exchange: string(20) NULL
name: string(255) NULL
currency: string(3) NULL
fiscal_year_end: tinyint unsigned NULL (1-12)
financials_collected_at: datetime NULL
quarters_collected_at: datetime NULL
valuation_collected_at: datetime NULL
profile_collected_at: datetime NULL
created_at: datetime NOT NULL
updated_at: datetime NOT NULL
```

**Relations:**
```php
public function getIndustry(): ActiveQuery
{
    return $this->hasOne(Industry::class, ['id' => 'industry_id']);
}

public function getAnnualFinancials(): ActiveQuery
{
    return $this->hasMany(AnnualFinancial::class, ['company_id' => 'id']);
}

public function getQuarterlyFinancials(): ActiveQuery
{
    return $this->hasMany(QuarterlyFinancial::class, ['company_id' => 'id']);
}

public function getValuationSnapshots(): ActiveQuery
{
    return $this->hasMany(ValuationSnapshot::class, ['company_id' => 'id']);
}
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findById($id)` | Remove. Use `Company::findOne($id)` |
| `findByTicker($ticker)` | `byTicker(string $ticker): self` |
| `findAll()` | `alphabetical(): self` |
| `findByIndustry($id)` | `inIndustry(int $industryId): self` |
| `countByIndustry($id)` | Use `Company::find()->inIndustry($id)->count()` |

**Write methods (→ Model):**
| Old Method | New Model Method |
|------------|------------------|
| `findOrCreate($ticker)` | Move to handler (involves read + write) |
| `updateStaleness($id, $field, $at)` | `public function markCollected(string $field): bool` |

**Model methods:**
```php
private const STALENESS_FIELDS = [
    'financials_collected_at',
    'quarters_collected_at',
    'valuation_collected_at',
    'profile_collected_at',
];

public function markCollected(string $field): bool
{
    if (!in_array($field, self::STALENESS_FIELDS, true)) {
        throw new \InvalidArgumentException("Invalid staleness field: $field");
    }
    $this->$field = date('Y-m-d H:i:s');
    return $this->save(false, [$field]);
}
```

---

### 2.3 DataGap

**Files:**
- Create: `yii/src/models/DataGap.php`
- Refactor: `yii/src/queries/DataGapQuery.php` → extend `ActiveQuery`

**Schema:**
```
id: bigint unsigned PK
company_id: bigint unsigned NOT NULL FK→company
data_type: string(50) NOT NULL
gap_reason: string(255) NOT NULL
first_detected: datetime NOT NULL
last_checked: datetime NOT NULL
check_count: int unsigned NOT NULL DEFAULT 1
notes: string(500) NULL
created_at: datetime NOT NULL
updated_at: datetime NOT NULL

UNIQUE: (company_id, data_type)
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findByCompanyAndType($companyId, $type)` | `forCompany(int $companyId): self` + `ofType(string $type): self` |

**Write methods (→ Model):**
| Old Method | New Model Method |
|------------|------------------|
| `upsert(...)` | `public static function recordGap(int $companyId, string $type, string $reason): self` |
| `delete(...)` | Use `$dataGap->delete()` |

---

## Phase 3: Financial Models

All depend on Company from Phase 2.

### 3.1 AnnualFinancial

**Files:**
- Create: `yii/src/models/AnnualFinancial.php`
- Refactor: `yii/src/queries/AnnualFinancialQuery.php` → extend `ActiveQuery`

**Schema:** (24 columns - see database schema reference)

**Key columns:**
```
id, company_id, fiscal_year, period_end_date, revenue, cost_of_revenue,
gross_profit, operating_income, ebitda, net_income, eps, operating_cash_flow,
capex, free_cash_flow, dividends_paid, total_assets, total_liabilities,
total_equity, total_debt, cash_and_equivalents, net_debt, shares_outstanding,
currency, source_adapter, source_url, collected_at, provider_id, version,
is_current, created_at

UNIQUE: (company_id, fiscal_year, version)
FK: company_id→company, provider_id→data_source
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findCurrentByCompanyAndYear($companyId, $year)` | `forCompany(int $companyId): self` + `forYear(int $year): self` + `current(): self` |
| `findAllCurrentByCompany($companyId)` | `current(): self` (scope for `is_current = 1`) |
| `findLatestYear($companyId)` | Keep as `->max('fiscal_year')` or model method |
| `exists($companyId, $year)` | Use `->exists()` |

**Write methods:**
| Old Method | New Model Method |
|------------|------------------|
| `insert($data)` | Use `$model->save()` |
| `markNotCurrent($companyId, $year)` | `public static function markPreviousVersionsNotCurrent(int $companyId, int $year): void` |

---

### 3.2 QuarterlyFinancial

**Files:**
- Create: `yii/src/models/QuarterlyFinancial.php`
- Refactor: `yii/src/queries/QuarterlyFinancialQuery.php` → extend `ActiveQuery`

**Schema:** Similar to AnnualFinancial with `fiscal_quarter` field.

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findLastFourQuarters($companyId)` | `forCompany(int $companyId): self` + `current(): self` + `latestQuarters(int $count): self` |
| `findCurrentByCompanyAndQuarter(...)` | `forCompany()->forYearQuarter(int $year, int $quarter)->current()` |
| `findAllCurrentByCompany(...)` | `forCompany()->current()` |

---

### 3.3 TtmFinancial

**Files:**
- Create: `yii/src/models/TtmFinancial.php`
- Refactor: `yii/src/queries/TtmFinancialQuery.php` → extend `ActiveQuery`

**Schema:**
```
id, company_id, as_of_date, revenue, gross_profit, operating_income, ebitda,
net_income, operating_cash_flow, capex, free_cash_flow, q1_period_end,
q2_period_end, q3_period_end, q4_period_end, currency, calculated_at,
provider_id, created_at

UNIQUE: (company_id, as_of_date)
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findByCompanyAndDate($companyId, $date)` | `forCompany(int $companyId): self` + `asOfDate(string $date): self` |

**Write methods:**
| Old Method | New Model Method |
|------------|------------------|
| `upsert(...)` | `public static function upsertForCompany(int $companyId, string $date, array $data): self` |

---

### 3.4 ValuationSnapshot

**Files:**
- Create: `yii/src/models/ValuationSnapshot.php`
- Refactor: `yii/src/queries/ValuationSnapshotQuery.php` → extend `ActiveQuery`

**Schema:** (24 columns including valuation multiples)

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findByCompanyAndDate(...)` | `forCompany(int $companyId): self` + `onDate(string $date): self` |
| `findLatestByCompany(...)` | `forCompany()->latest(): self` |

---

### 3.5 MacroIndicator

**Files:**
- Create: `yii/src/models/MacroIndicator.php`
- Refactor: `yii/src/queries/MacroIndicatorQuery.php` → extend `ActiveQuery`

**Schema:**
```
id, indicator_key, indicator_date, value, unit, source_adapter, source_url,
collected_at, provider_id, created_at

UNIQUE: (indicator_key, indicator_date)
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findByKeyAndDate($key, $date)` | `forKey(string $key): self` + `onDate(string $date): self` |
| `findLatestByKey($key)` | `forKey($key)->latest(): self` |

---

### 3.6 PriceHistory

**Files:**
- Create: `yii/src/models/PriceHistory.php`
- Refactor: `yii/src/queries/PriceHistoryQuery.php` → extend `ActiveQuery`

**Schema:**
```
id, symbol, symbol_type, price_date, open, high, low, close, adjusted_close,
volume, currency, source_adapter, collected_at, provider_id, created_at

UNIQUE: (symbol, price_date)
```

**Constants:**
```php
public const TYPE_STOCK = 'stock';
public const TYPE_INDEX = 'index';
public const TYPE_COMMODITY = 'commodity';
```

**Query methods:**
| Old Method | New ActiveQuery Method |
|------------|------------------------|
| `findBySymbolAndDate($symbol, $date)` | `forSymbol(string $symbol): self` + `onDate(string $date): self` |
| `findLatestBySymbol($symbol)` | `forSymbol($symbol)->latest(): self` |
| `findExistingDates($symbol, $start, $end)` | `forSymbol($symbol)->inDateRange($start, $end)->select('price_date')` |

**Write methods:**
| Old Method | New Model Method |
|------------|------------------|
| `insert($data)` | Use `$model->save()` |
| `bulkInsert($rows)` | `public static function bulkInsert(array $rows): int` using `Yii::$app->db->createCommand()->batchInsert()` |

---

### 3.7 AnalysisReport

**Files:**
- Create: `yii/src/models/AnalysisReport.php`
- Create: `yii/src/queries/AnalysisReportQuery.php`

**Schema:**
```
id: int PK
industry_id: bigint unsigned NOT NULL FK→industry
report_id: string(50) NOT NULL UNIQUE
rating: string(20) NOT NULL
rule_path: string(50) NOT NULL
report_json: json NOT NULL
generated_at: datetime NOT NULL
data_as_of: datetime NULL
created_at: datetime NOT NULL
```

**Note:** This replaces `AnalysisReportRepository`. Read the existing repository to understand method signatures before migrating.

---

## Phase 4: Update Consumers

For each model migration:

1. **Find usages:** `grep -r "new CompanyQuery" yii/src/` and `grep -r "CompanyQuery::" yii/src/`
2. **Update constructors:** Remove DI of old Query class
3. **Update method calls:**
   - `$this->companyQuery->findById($id)` → `Company::findOne($id)`
   - `$this->companyQuery->findByTicker($ticker)` → `Company::find()->byTicker($ticker)->one()`
   - `$this->companyQuery->findAll()` → `Company::find()->alphabetical()->all()`
4. **Remove DI container entries** from `yii/config/container.php`

---

## Phase 5: Cleanup

1. Delete old Query classes that are now empty (all methods migrated)
2. Delete corresponding interface files if they exist
3. Run full test suite: `docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit`
4. Run linter: `docker exec aimm_yii vendor/bin/php-cs-fixer fix`

---

## Verification Checklist

For each model:

- [ ] Model file created at `yii/src/models/{ModelName}.php`
- [ ] Model is `final class` with `declare(strict_types=1)`
- [ ] PHPDoc has all `@property` annotations matching schema
- [ ] PHPDoc has `@property-read` for relations
- [ ] `tableName()` returns correct table with `{{%}}` prefix
- [ ] `rules()` covers all required fields and validations
- [ ] `find()` returns typed Query class
- [ ] Relations defined with correct foreign keys
- [ ] Query class extends `ActiveQuery` with `@method` annotations
- [ ] Query methods are chainable (return `self`)
- [ ] Write methods moved to model or deleted
- [ ] DI container entry removed
- [ ] All consumers updated
- [ ] Tests pass

---

## Implementation Order

Execute in this order to avoid dependency issues:

1. **Sector** (no deps)
2. **CollectionPolicy** (no deps)
3. **FxRate** (no deps)
4. **Industry** (depends on Sector, CollectionPolicy)
5. **Company** (depends on Industry)
6. **DataGap** (depends on Company)
7. **AnnualFinancial** (depends on Company, DataSource)
8. **QuarterlyFinancial** (depends on Company, DataSource)
9. **TtmFinancial** (depends on Company, DataSource)
10. **ValuationSnapshot** (depends on Company, DataSource)
11. **MacroIndicator** (depends on DataSource)
12. **PriceHistory** (depends on DataSource)
13. **AnalysisReport** (depends on Industry)

**Note:** DataSource model already exists. Ensure its Query class is migrated if needed.

---

## Testing Strategy

1. **Unit tests** for each Query class method (chainability, correct SQL)
2. **Integration tests** for model save/delete operations
3. **Existing tests** should continue to pass after consumer updates

Run after each model migration:
```bash
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit
docker exec aimm_yii vendor/bin/php-cs-fixer fix --dry-run
```
