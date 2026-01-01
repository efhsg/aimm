    # Prompt: Implement Company Dossier System

    ## Role

    You are a **Senior PHP Engineer** implementing a database-backed financial data storage system for the AIMM project. You specialize in:

    - PHP 8.x with strict types
    - Yii2 framework (DI container, migrations, console commands)
    - MySQL/MariaDB schema design
    - Codeception unit testing
    - Financial data systems with provenance tracking

    ## Context

    AIMM currently uses ephemeral JSON "datapack" files to store collected financial data. Each collection run re-fetches all data, wasting API credits and time. We're replacing this with a persistent **Company Dossier** database that stores data once and only fetches what's missing.

    ## Project Conventions

    ### Folder Structure (STRICT)

    | Folder | Purpose |
    |--------|---------|
    | `handlers/` | Business logic, orchestration |
    | `queries/` | Database queries (read operations) |
    | `validators/` | Validation logic |
    | `transformers/` | Data shape conversion |
    | `factories/` | Object construction |
    | `dto/` | Typed data transfer objects |
    | `clients/` | External HTTP clients |
    | `adapters/` | External API → internal DTO mapping |
    | `enums/` | Enumerated types |
    | `exceptions/` | Custom exceptions |
    | `commands/` | Console controllers |
    | `events/` | Event classes |

    **BANNED:** `services/`, `helpers/`, `utils/`, `components/` (except Yii framework)

    ### Coding Standards

    - `declare(strict_types=1);` in ALL files
    - Type hints on ALL parameters and return types
    - No business logic in controllers — delegate to handlers
    - No magic strings — use constants or enums
    - No silent failures — log or throw
    - PSR-12 formatting

    ### Testing Requirements

    - Unit tests for: queries, validators, transformers, factories, handlers
    - Test naming: `testCalculatesTtmWhenFourQuartersPresent`, `testReturnsNullWhenQuartersMissing`
    - Run tests: `docker exec aimm_yii vendor/bin/codecept run unit`
    - Run linter: `docker exec aimm_yii vendor/bin/php-cs-fixer fix`

    ### Commit Format

    ```
    TYPE(scope): description

    [optional body]
    ```

    Types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`

    ---

    ## Design Document

    The full design is in `docs/design/company_dossier.md`. Key elements:

    ### Database Schema (10 tables)

    1. **company** — Canonical company record with staleness timestamps
    2. **annual_financial** — Yearly financials with versioning (`is_current`, `version`)
    3. **quarterly_financial** — Quarterly financials with versioning
    4. **ttm_financial** — Derived TTM data (sum of last 4 quarters)
    5. **valuation_snapshot** — Daily valuation metrics with retention tiers
    6. **price_history** — Stock/commodity/index prices
    7. **fx_rate** — ECB FX rates for currency conversion
    8. **macro_indicator** — Rig counts, inventories, etc.
    9. **collection_attempt** — Audit log of all fetch attempts
    10. **data_gap** — Tracks known missing data

    ### Key PHP Components

    1. **Query Classes** — `CompanyQuery`, `AnnualFinancialQuery`, `QuarterlyFinancialQuery`, `TtmFinancialQuery`, `ValuationSnapshotQuery`, `FxRateQuery`, `PriceHistoryQuery`, `MacroIndicatorQuery`, `CollectionAttemptQuery`, `DataGapQuery`

    2. **Handler Classes**
    - `TtmCalculator` — Calculates TTM from quarterly data
    - `RecalculateTtmOnQuarterlyCollected` — Event listener for TTM updates

    3. **Transformer Classes**
    - `CurrencyConverter` — Converts between currencies using ECB rates with batch loading

    4. **Console Commands**
    - `CompressValuationController` — Already implemented, compresses old snapshots

    5. **Events**
    - `QuarterlyFinancialsCollectedEvent` — Triggers TTM recalculation

    ---

    ## Implementation Phases

    ### Phase 1: Schema & Migration

    **Deliverables:**
    - [ ] Yii migration creating all 10 tables
    - [ ] Proper indexes, foreign keys, and constraints
    - [ ] Run migration successfully

    **File:** `migrations/m{timestamp}_create_dossier_schema.php`

    **Validation:**
    ```bash
    docker exec aimm_yii php yii migrate
    ```

    ---

    ### Phase 2: Query Classes

    **Deliverables:**
    - [ ] `yii/src/queries/CompanyQuery.php`
    - [ ] `yii/src/queries/AnnualFinancialQuery.php`
    - [ ] `yii/src/queries/QuarterlyFinancialQuery.php`
    - [ ] `yii/src/queries/TtmFinancialQuery.php`
    - [ ] `yii/src/queries/ValuationSnapshotQuery.php`
    - [ ] `yii/src/queries/FxRateQuery.php` (design doc has reference impl)
    - [ ] `yii/src/queries/PriceHistoryQuery.php`
    - [ ] `yii/src/queries/MacroIndicatorQuery.php`
    - [ ] `yii/src/queries/CollectionAttemptQuery.php`
    - [ ] `yii/src/queries/DataGapQuery.php`

    **Required Methods per Query Class:**

    ```php
    // CompanyQuery
    findById(int $id): ?array
    findByTicker(string $ticker): ?array
    findOrCreate(string $ticker): int  // Returns company_id
    updateStaleness(int $id, string $field, DateTimeImmutable $at): void

    // AnnualFinancialQuery
    findCurrentByCompanyAndYear(int $companyId, int $year): ?array
    findAllCurrentByCompany(int $companyId): array
    exists(int $companyId, int $year): bool
    insert(array $data): int
    markNotCurrent(int $companyId, int $year): void

    // QuarterlyFinancialQuery
    findLastFourQuarters(int $companyId, DateTimeImmutable $asOfDate): array
    findCurrentByCompanyAndQuarter(int $companyId, int $year, int $quarter): ?array
    insert(array $data): int

    // TtmFinancialQuery
    findByCompanyAndDate(int $companyId, DateTimeImmutable $date): ?array
    upsert(TtmFinancialRecord $record): void

    // ValuationSnapshotQuery
    findByCompanyAndDate(int $companyId, DateTimeImmutable $date): ?array
    findLatestByCompany(int $companyId): ?array
    insert(array $data): int

    // FxRateQuery (see design doc)
    findClosestRate(string $quoteCurrency, DateTimeImmutable $asOfDate): ?float
    findRatesInRange(array $currencies, DateTimeImmutable $min, DateTimeImmutable $max): array
    ```

    **Unit Tests:** One test file per query class with coverage of happy path + edge cases.

    ---

    ### Phase 3: DTOs & Records

    **Deliverables:**
    - [ ] `yii/src/dto/TtmFinancialRecord.php`
    - [ ] `yii/src/dto/ValuationSnapshotRecord.php`
    - [ ] `yii/src/dto/AnnualFinancialRecord.php`
    - [ ] `yii/src/dto/QuarterlyFinancialRecord.php`

    **Example Structure:**

    ```php
    <?php

    declare(strict_types=1);

    namespace app\dto;

    use DateTimeImmutable;

    final readonly class TtmFinancialRecord
    {
        public function __construct(
            public int $companyId,
            public DateTimeImmutable $asOfDate,
            public ?float $revenue,
            public ?float $grossProfit,
            public ?float $operatingIncome,
            public ?float $ebitda,
            public ?float $netIncome,
            public ?float $operatingCashFlow,
            public ?float $capex,
            public ?float $freeCashFlow,
            public ?DateTimeImmutable $q1PeriodEnd,
            public ?DateTimeImmutable $q2PeriodEnd,
            public ?DateTimeImmutable $q3PeriodEnd,
            public ?DateTimeImmutable $q4PeriodEnd,
            public string $currency,
            public DateTimeImmutable $calculatedAt,
        ) {}
    }
    ```

    ---

    ### Phase 4: Handlers & Transformers

    **Deliverables:**
    - [ ] `yii/src/handlers/dossier/TtmCalculator.php` (design doc has reference impl)
    - [ ] `yii/src/handlers/dossier/RecalculateTtmOnQuarterlyCollected.php`
    - [ ] `yii/src/transformers/CurrencyConverter.php` (design doc has reference impl)

    **Unit Tests:**
    - `TtmCalculatorTest.php` — Test consecutive quarter detection, null handling, sum calculations
    - `CurrencyConverterTest.php` — Test same currency, cross rates, batch conversion, caching

    ---

    ### Phase 5: Events & Wiring

    **Deliverables:**
    - [ ] `yii/src/events/QuarterlyFinancialsCollectedEvent.php`
    - [ ] Register event handler in `yii/config/container.php`

    **Event Structure:**

    ```php
    <?php

    declare(strict_types=1);

    namespace app\events;

    use DateTimeImmutable;

    final readonly class QuarterlyFinancialsCollectedEvent
    {
        public function __construct(
            public int $companyId,
            public DateTimeImmutable $periodEndDate,
        ) {}
    }
    ```

    ---

    ### Phase 6: Backfill Command

    **Deliverables:**
    - [ ] `yii/src/commands/BackfillDossierController.php` (design doc has skeleton)
    - [ ] Complete `importFinancials()`, `importQuarters()`, `importValuation()` methods

    **Validation:**
    ```bash
    docker exec aimm_yii php yii backfill-dossier
    ```

    ---

    ### Phase 7: Integration

    **Deliverables:**
    - [ ] Modify `CollectCompanyHandler` to write to dossier tables (in addition to datapack for now)
    - [ ] Modify `CollectMacroHandler` to write to `macro_indicator` and `price_history`
    - [ ] Add staleness checks before fetching

    **Key Logic:**
    ```php
    // Before fetching annual financials
    $latestYear = $this->annualQuery->findLatestYear($companyId);
    $currentYear = (int) date('Y');

    // Only fetch missing years
    for ($year = $latestYear + 1; $year <= $currentYear; $year++) {
        $this->collectAnnualFinancials($companyId, $year);
    }
    ```

    ---

    ## Existing Code References

    Before implementing, examine these existing files for patterns:

    - `yii/src/queries/IndustryConfigQuery.php` — Query class pattern
    - `yii/src/handlers/collection/CollectCompanyHandler.php` — Handler pattern
    - `yii/src/dto/IndustryConfig.php` — DTO pattern
    - `yii/src/commands/CompressValuationController.php` — Console command pattern
    - `yii/src/transformers/` — Transformer pattern

    ---

    ## Acceptance Criteria

    1. All 10 tables created via migration
    2. All query classes implemented with unit tests
    3. TTM calculation works and is triggered by event
    4. Currency conversion works with batch loading
    5. Backfill command successfully imports existing datapacks
    6. All unit tests pass: `docker exec aimm_yii vendor/bin/codecept run unit`
    7. Linter passes: `docker exec aimm_yii vendor/bin/php-cs-fixer fix`

    ---

    ## Important Notes

    1. **Do not create `services/` folder** — Use `handlers/` for orchestration
    2. **Do not use Active Record** — Use raw queries via `yii\db\Connection`
    3. **Always use transactions** for multi-table writes
    4. **Never fabricate financial data** — Document gaps in `data_gap` table
    5. **Test with mocked DB connection** — Don't require real database for unit tests
    6. **Register all classes in DI container** — `yii/config/container.php`

    ---

    ## Getting Started

    1. Read the full design document: `docs/design/company_dossier.md`
    2. Examine existing query classes for patterns
    3. Start with Phase 1 (migration) and validate it works
    4. Proceed phase by phase, running tests after each phase
    5. Commit after each completed phase with proper commit message

    Begin with Phase 1: Create the database migration.
