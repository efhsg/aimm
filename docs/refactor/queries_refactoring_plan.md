# Refactoring Plan: Migrate Queries to Yii 2 ActiveQuery Pattern

**Date:** 2026-01-13
**Status:** Planned

## Overview

The codebase currently implements a Repository pattern using raw SQL via `yii\db\Connection` in classes located in `yii/src/queries/`. To align with Yii 2 best practices and project conventions, these should be refactored to use the **ActiveRecord + ActiveQuery** pattern.

**Target Pattern:**
- **Model:** Extends `yii\db\ActiveRecord`. Defines schema, rules, and relations.
- **Query:** Extends `yii\db\ActiveQuery`. Defines custom finding logic.
- **Usage:** `Model::find()->customMethod()->all()` instead of `QueryService->customMethod()`.

## Refactoring Candidates

The following classes have been identified for refactoring. They currently use raw SQL usage.

### 1. Core Domain Entities

| Current Class | Target Model | Target Query Class | Notes |
| :--- | :--- | :--- | :--- |
| `AnnualFinancialQuery` | `AnnualFinancial` | `AnnualFinancialQuery` | Extend `ActiveQuery`. Move `findCurrent...` methods here. |
| `CollectionAttemptQuery` | `CollectionAttempt` | `CollectionAttemptQuery` | Create Model. Extend `ActiveQuery`. |
| `CollectionPolicyQuery` | `CollectionPolicy` | `CollectionPolicyQuery` | Extend `ActiveQuery`. |
| `CollectionRunRepository` | `CollectionRun` | `CollectionRunQuery` | **High Complexity**. Handles writes/updates. Move write logic to Model methods or Service, read logic to ActiveQuery. |
| `CompanyQuery` | `Company` | `CompanyQuery` | Extend `ActiveQuery`. |
| `DataGapQuery` | `DataGap` | `DataGapQuery` | Extend `ActiveQuery`. |
| `DataSourceQuery` | `DataSource` | `DataSourceQuery` | Extend `ActiveQuery`. |
| `FxRateQuery` | `FxRate` | `FxRateQuery` | Extend `ActiveQuery`. |
| `IndustryQuery` | `Industry` | `IndustryQuery` | Extend `ActiveQuery`. |
| `MacroIndicatorQuery` | `MacroIndicator` | `MacroIndicatorQuery` | Extend `ActiveQuery`. |
| `PriceHistoryQuery` | `PriceHistory` | `PriceHistoryQuery` | Extend `ActiveQuery`. |
| `QuarterlyFinancialQuery` | `QuarterlyFinancial` | `QuarterlyFinancialQuery` | Extend `ActiveQuery`. |
| `SectorQuery` | `Sector` | `SectorQuery` | Extend `ActiveQuery`. |
| `TtmFinancialQuery` | `TtmFinancial` | `TtmFinancialQuery` | Extend `ActiveQuery`. |
| `ValuationSnapshotQuery` | `ValuationSnapshot` | `ValuationSnapshotQuery` | Extend `ActiveQuery`. |

### 2. Complex Repositories & Services

These classes contain mixed read/write logic or complex aggregations.

| Current Class | Proposed Action |
| :--- | :--- |
| `AnalysisReportRepository` | Split. Read logic -> `AnalysisReportQuery` (ActiveQuery). Write logic -> `AnalysisReportService` or Model methods. |
| `PdfJobRepository` | Refactor to `PdfJobQuery` for reads. Use `PdfJob` model methods for state transitions (`transitionTo`, `complete`, `fail`). |
| `SourceBlockRepository` | Refactor to `SourceBlockQuery` for reads (`isBlocked`). Use Model methods for `recordBlock`. |
| `IndustryListQuery` | This is a "Read Model" / DTO projection. **Keep as is** or refactor to use `ActiveQuery` with `asArray()` and `select()` for performance if preferred, but raw SQL is acceptable for complex reporting queries if documented. *Recommendation: Refactor to ActiveQuery for consistency if performance permits.* |
| `IndustryMemberQuery` | Move logic to `Industry` and `Company` ActiveRecord relations and helper methods (e.g., `link()`, `unlink()`). |

### 3. Service Facades (Dependent Classes)

These classes consume the queries above and will need their constructor dependencies updated.

- `IndustryAnalysisEligibilityQuery`
- `IndustryAnalysisQuery`

## detailed Implementation Steps

For each entity (e.g., `Company`):

1.  **Check/Create Model:** Ensure `yii/src/models/Company.php` exists and extends `ActiveRecord`.
2.  **Create/Update Query:** Ensure `yii/src/queries/CompanyQuery.php` extends `yii\db\ActiveQuery`.
3.  **Wire Model:** Add `public static function find(): CompanyQuery` to the Model.
4.  **Migrate Logic:**
    -   Move `SELECT` logic from the old `Query` class to the new `ActiveQuery` class.
    -   Replace raw SQL with Query Builder syntax (e.g., `->where(...)`).
    -   Move `INSERT/UPDATE` logic to Model methods (e.g., `save()`, `updateAttributes()`) or keep in a specific Service if complex transaction logic exists.
5.  **Refactor Consumers & Cleanup:** 
    -   Update all usages in Controllers/Services to use `Company::find()->...` or `new Company(...)` for writes.
    -   **Remove DI container definitions** (`yii/config/container.php`) for the converted Query class, as `ActiveQuery` is instantiated via `Model::find()` and not injected as a singleton service.
6.  **Delete Old Class:** Remove the raw SQL repository class if it becomes empty.

## Special Handling: `CollectionAttempt`

As identified in the specific request:
1.  Create `yii/src/models/CollectionAttempt.php`.
2.  Refactor `yii/src/queries/CollectionAttemptQuery.php` to extend `ActiveQuery`.
3.  Connect them via `CollectionAttempt::find()`.
