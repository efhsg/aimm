# Refactoring Plan: Migrate Queries to Yii 2 ActiveQuery Pattern

**Date:** 2026-01-13
**Status:** In Progress

## Overview

The codebase currently implements a Repository pattern using raw SQL via `yii\db\Connection` in classes located in `yii/src/queries/`. To align with Yii 2 best practices and project conventions, these should be refactored to use the **ActiveRecord + ActiveQuery** pattern.

**Target Pattern:**
- **Model:** `ModelName` extends `yii\db\ActiveRecord`. Defines schema, rules, and relations.
- **Query:** `ModelNameQuery` extends `yii\db\ActiveQuery`. Defines custom finding logic.
- **Usage:** `Model::find()->customMethod()->all()` instead of `QueryService->customMethod()`.

**Read vs Write Separation:**
- **Read logic** (SELECT queries) → `ModelNameQuery` extending `ActiveQuery`
- **Write logic** (INSERT/UPDATE/DELETE) → Model methods (`save()`, `delete()`) or domain-specific methods on the model

## Refactoring Candidates

The following classes have been identified for refactoring. They currently use raw SQL.

### 1. Core Domain Entities

| Current Class | Target Model | Target Query Class | Model Exists | Status | Notes |
| :--- | :--- | :--- | :---: | :---: | :--- |
| `CollectionAttemptQuery` | `CollectionAttempt` | `CollectionAttemptQuery` | ✅ | ✅ Done | Already migrated. |
| `CollectionRunRepository` | `CollectionRun` | `CollectionRunQuery` | ✅ | Pending | **High Complexity**. Move write logic to Model, read logic to ActiveQuery. |
| `DataSourceQuery` | `DataSource` | `DataSourceQuery` | ✅ | Pending | Model exists. Extend `ActiveQuery`. |
| `CompanyQuery` | `Company` | `CompanyQuery` | ❌ | Pending | Create Model. Move `updateStaleness()`, `findOrCreate()` to Model. |
| `IndustryQuery` | `Industry` | `IndustryQuery` | ❌ | Pending | Create Model. Move `activate()`, `deactivate()`, `assignPolicy()` to Model. |
| `SectorQuery` | `Sector` | `SectorQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `AnnualFinancialQuery` | `AnnualFinancial` | `AnnualFinancialQuery` | ❌ | Pending | Create Model. Move `findCurrent...` methods to ActiveQuery. |
| `QuarterlyFinancialQuery` | `QuarterlyFinancial` | `QuarterlyFinancialQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `TtmFinancialQuery` | `TtmFinancial` | `TtmFinancialQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `ValuationSnapshotQuery` | `ValuationSnapshot` | `ValuationSnapshotQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `CollectionPolicyQuery` | `CollectionPolicy` | `CollectionPolicyQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `DataGapQuery` | `DataGap` | `DataGapQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `FxRateQuery` | `FxRate` | `FxRateQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `MacroIndicatorQuery` | `MacroIndicator` | `MacroIndicatorQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |
| `PriceHistoryQuery` | `PriceHistory` | `PriceHistoryQuery` | ❌ | Pending | Create Model. Extend `ActiveQuery`. |

### 2. Complex Repositories & Services

These classes contain mixed read/write logic or complex aggregations.

| Current Class | Model Exists | Status | Proposed Action |
| :--- | :---: | :---: | :--- |
| `PdfJobRepository` | ✅ | Pending | Refactor to `PdfJobQuery` for reads. Use `PdfJob` model methods for state transitions (`transitionTo`, `complete`, `fail`). |
| `SourceBlockRepository` | ✅ | Pending | Refactor to `SourceBlockQuery` for reads (`isBlocked`). Use Model methods for `recordBlock`. |
| `AnalysisReportRepository` | ❌ | Pending | Split. Read logic → `AnalysisReportQuery`. Write logic → Model methods or handler. |
| `IndustryListQuery` | N/A | Pending | Read-model/DTO projection with complex aggregates. Options: (1) Keep raw SQL if performance-critical, (2) Refactor to ActiveQuery with `->select()`, `->leftJoin()`, `->groupBy()`, `->asArray()`. Recommend option 2 for consistency. |
| `IndustryMemberQuery` | N/A | Pending | Move logic to `Industry` and `Company` ActiveRecord relations (`link()`, `unlink()`). |

### 3. Service Facades (Dependent Classes)

These classes consume the queries above and will need their constructor dependencies updated after migration.

- `IndustryAnalysisEligibilityQuery`
- `IndustryAnalysisQuery`

## Implementation Steps

For each entity (e.g., `Company`):

1.  **Check/Create Model:** Ensure `yii/src/models/Company.php` exists and extends `ActiveRecord`.
2.  **Create/Update Query:** Ensure `yii/src/queries/CompanyQuery.php` extends `yii\db\ActiveQuery`.
3.  **Wire Model:** Add `public static function find(): CompanyQuery` to the Model.
4.  **Migrate Logic:**
    -   Move `SELECT` logic from the old `Query` class to the new `ActiveQuery` class as chainable methods.
    -   Replace raw SQL with Query Builder syntax (e.g., `->andWhere(...)`).
    -   Move `INSERT/UPDATE/DELETE` logic to Model methods (e.g., `save()`, `delete()`, or domain methods like `activate()`).
5.  **Refactor Consumers & Cleanup:**
    -   Update all usages in Controllers/Handlers to use `Company::find()->...` for reads.
    -   Use `$model->save()` or model methods for writes.
    -   **Remove DI container definitions** (`yii/config/container.php`) for the converted Query class.
6.  **Delete Old Class:** Remove the raw SQL repository class if it becomes empty.

## Completed Migrations

### `CollectionAttempt` ✅

Migrated as the reference implementation:
- Model: `yii/src/models/CollectionAttempt.php` — extends `ActiveRecord`
- Query: `yii/src/queries/CollectionAttemptQuery.php` — extends `ActiveQuery`
- Wired via `CollectionAttempt::find(): CollectionAttemptQuery`
