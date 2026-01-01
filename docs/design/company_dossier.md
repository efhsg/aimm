# Company Dossier Design

## Overview

The Company Dossier is a database-backed system for persistent storage of company financial data. It replaces the ephemeral datapack JSON files with a structured, versioned, and incrementally updatable data store.

### Key Benefits

| Aspect | Old (Datapacks) | New (Dossier) |
|--------|-----------------|---------------|
| Historical financials | Re-fetched every run | Stored once, reused |
| API usage | High (all data each run) | Low (only missing periods) |
| Collection speed | Slow (full fetch) | Fast (incremental) |
| Data lineage | Single snapshot | Full history with versions |
| Currency handling | Inconsistent | Transparent conversion |

### Design Principles

1. **Immutable history with versioning** - Historical data is append-only; corrections create new versions
2. **Clear provenance** - Every datapoint tracks source, collection time, and confidence
3. **Smart staleness** - Each data type has its own freshness rules
4. **Sparse storage** - Only store what we have; `NULL` means not collected, explicit `not_found` marker for attempted but missing
5. **Native currency storage** - Store in reporting currency, convert transparently at query time
6. **Efficient analysis queries** - Denormalized where it helps, indexed for common access patterns

---

## Schema Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         COMPANY DOSSIER                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────┐     ┌───────────────────┐     ┌────────────────┐ │
│  │   company    │────<│ annual_financial  │     │   fx_rate      │ │
│  │              │     │ (native currency) │     │  (ECB rates)   │ │
│  │ - ticker     │     │ - versioned       │     │                │ │
│  │ - exchange   │     └───────────────────┘     └───────┬────────┘ │
│  │ - currency   │                                       │          │
│  │ - staleness  │     ┌───────────────────┐             │          │
│  │   timestamps │────<│quarterly_financial│             │          │
│  └──────┬───────┘     │ (native currency) │     ┌───────▼────────┐ │
│         │             │ - versioned       │     │ fn_get_fx_rate │ │
│         │             └─────────┬─────────┘     │ (transparent   │ │
│         │                       │               │  conversion)   │ │
│         │             ┌─────────▼─────────┐     └────────────────┘ │
│         │             │   ttm_financial   │                        │
│         │             │ (derived, auto-   │                        │
│         │             │  updated)         │                        │
│         │             └───────────────────┘                        │
│         │                                                          │
│         │             ┌───────────────────┐     ┌────────────────┐ │
│         └────────────<│valuation_snapshot │     │ price_history  │ │
│                       │ - daily/weekly/   │     │ - stocks       │ │
│                       │   monthly tiers   │     │ - commodities  │ │
│                       └───────────────────┘     │ - indices      │ │
│                                                 └────────────────┘ │
│  ┌────────────────┐   ┌───────────────────┐                        │
│  │ macro_indicator│   │collection_attempt │                        │
│  │ - rig_count    │   │ (audit log)       │                        │
│  │ - inventory    │   └───────────────────┘                        │
│  └────────────────┘                                                │
│                       ┌───────────────────┐                        │
│                       │    data_gap       │                        │
│                       │ (known missing)   │                        │
│                       └───────────────────┘                        │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Core Tables

### `company`

The canonical company record. One per ticker we track.

```sql
CREATE TABLE company (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticker          VARCHAR(20) NOT NULL,
    exchange        VARCHAR(20) NULL,          -- NYSE, NASDAQ, AMS, LSE, etc.
    name            VARCHAR(255) NULL,
    sector          VARCHAR(100) NULL,
    industry        VARCHAR(100) NULL,
    currency        CHAR(3) NULL,              -- Reporting currency (USD, EUR, etc.)
    fiscal_year_end TINYINT UNSIGNED NULL,     -- Month (1-12) fiscal year ends

    -- Staleness tracking
    financials_collected_at     DATETIME NULL,
    quarters_collected_at       DATETIME NULL,
    valuation_collected_at      DATETIME NULL,
    profile_collected_at        DATETIME NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_ticker (ticker),
    KEY idx_exchange (exchange),
    KEY idx_sector (sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `annual_financial`

One row per company per fiscal year. Immutable once written; corrections create new versions.

```sql
CREATE TABLE annual_financial (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      BIGINT UNSIGNED NOT NULL,
    fiscal_year     SMALLINT UNSIGNED NOT NULL,  -- e.g., 2023
    period_end_date DATE NOT NULL,               -- Actual period end (e.g., 2023-12-31)

    -- Income Statement
    revenue         DECIMAL(20,2) NULL,
    cost_of_revenue DECIMAL(20,2) NULL,
    gross_profit    DECIMAL(20,2) NULL,
    operating_income DECIMAL(20,2) NULL,
    ebitda          DECIMAL(20,2) NULL,
    net_income      DECIMAL(20,2) NULL,
    eps             DECIMAL(10,4) NULL,

    -- Cash Flow
    operating_cash_flow  DECIMAL(20,2) NULL,
    capex               DECIMAL(20,2) NULL,
    free_cash_flow      DECIMAL(20,2) NULL,
    dividends_paid      DECIMAL(20,2) NULL,

    -- Balance Sheet (end of period)
    total_assets        DECIMAL(20,2) NULL,
    total_liabilities   DECIMAL(20,2) NULL,
    total_equity        DECIMAL(20,2) NULL,
    total_debt          DECIMAL(20,2) NULL,
    cash_and_equivalents DECIMAL(20,2) NULL,
    net_debt            DECIMAL(20,2) NULL,

    -- Derived/Calculated
    shares_outstanding  BIGINT UNSIGNED NULL,

    -- Metadata
    currency        CHAR(3) NOT NULL,            -- Native reporting currency
    source_adapter  VARCHAR(50) NOT NULL,        -- 'fmp', 'yahoo', 'manual'
    source_url      VARCHAR(500) NULL,
    collected_at    DATETIME NOT NULL,
    version         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_current      BOOLEAN NOT NULL DEFAULT TRUE,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_year_version (company_id, fiscal_year, version),
    KEY idx_company_current (company_id, is_current, fiscal_year DESC),
    KEY idx_collected (collected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `quarterly_financial`

One row per company per fiscal quarter.

```sql
CREATE TABLE quarterly_financial (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      BIGINT UNSIGNED NOT NULL,
    fiscal_year     SMALLINT UNSIGNED NOT NULL,
    fiscal_quarter  TINYINT UNSIGNED NOT NULL,   -- 1, 2, 3, 4
    period_end_date DATE NOT NULL,

    -- Income Statement (quarterly)
    revenue         DECIMAL(20,2) NULL,
    gross_profit    DECIMAL(20,2) NULL,
    operating_income DECIMAL(20,2) NULL,
    ebitda          DECIMAL(20,2) NULL,
    net_income      DECIMAL(20,2) NULL,
    eps             DECIMAL(10,4) NULL,

    -- Cash Flow (quarterly)
    operating_cash_flow DECIMAL(20,2) NULL,
    capex              DECIMAL(20,2) NULL,
    free_cash_flow     DECIMAL(20,2) NULL,

    -- Metadata
    currency        CHAR(3) NOT NULL,
    source_adapter  VARCHAR(50) NOT NULL,
    source_url      VARCHAR(500) NULL,
    collected_at    DATETIME NOT NULL,
    version         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_current      BOOLEAN NOT NULL DEFAULT TRUE,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_quarter_version (company_id, fiscal_year, fiscal_quarter, version),
    KEY idx_company_current (company_id, is_current, fiscal_year DESC, fiscal_quarter DESC),
    KEY idx_period_end (period_end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `ttm_financial`

Trailing Twelve Months derived data. Auto-updated when quarters change.

```sql
CREATE TABLE ttm_financial (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      BIGINT UNSIGNED NOT NULL,
    as_of_date      DATE NOT NULL,              -- Date TTM was calculated for

    -- TTM Income Statement (sum of last 4 quarters)
    revenue         DECIMAL(20,2) NULL,
    gross_profit    DECIMAL(20,2) NULL,
    operating_income DECIMAL(20,2) NULL,
    ebitda          DECIMAL(20,2) NULL,
    net_income      DECIMAL(20,2) NULL,

    -- TTM Cash Flow
    operating_cash_flow DECIMAL(20,2) NULL,
    capex              DECIMAL(20,2) NULL,
    free_cash_flow     DECIMAL(20,2) NULL,

    -- Source quarters used (for audit)
    q1_period_end   DATE NULL,
    q2_period_end   DATE NULL,
    q3_period_end   DATE NULL,
    q4_period_end   DATE NULL,

    currency        CHAR(3) NOT NULL,
    calculated_at   DATETIME NOT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_date (company_id, as_of_date),
    KEY idx_company_recent (company_id, as_of_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### TtmCalculator Service

TTM calculation is handled in PHP for explicit, testable logic (not database triggers).

```php
<?php

declare(strict_types=1);

namespace app\handlers\dossier;

use app\queries\QuarterlyFinancialQuery;
use app\queries\TtmFinancialQuery;
use DateTimeImmutable;

/**
 * Calculates Trailing Twelve Months financials from quarterly data.
 *
 * Triggered by QuarterlyFinancialsCollectedEvent.
 */
final class TtmCalculator
{
    public function __construct(
        private readonly QuarterlyFinancialQuery $quarterlyQuery,
        private readonly TtmFinancialQuery $ttmQuery,
    ) {}

    /**
     * Recalculate TTM for a company as of a specific date.
     */
    public function calculate(int $companyId, DateTimeImmutable $asOfDate): ?TtmFinancialRecord
    {
        // Get last 4 quarters ending on or before asOfDate
        $quarters = $this->quarterlyQuery->findLastFourQuarters($companyId, $asOfDate);

        if (count($quarters) < 4) {
            return null; // Not enough data for TTM
        }

        // Verify quarters are consecutive (no gaps)
        if (!$this->areConsecutive($quarters)) {
            return null;
        }

        // Sum flow metrics (revenue, EBITDA, FCF, etc.)
        $ttm = new TtmFinancialRecord(
            companyId: $companyId,
            asOfDate: $asOfDate,
            revenue: $this->sumField($quarters, 'revenue'),
            grossProfit: $this->sumField($quarters, 'gross_profit'),
            operatingIncome: $this->sumField($quarters, 'operating_income'),
            ebitda: $this->sumField($quarters, 'ebitda'),
            netIncome: $this->sumField($quarters, 'net_income'),
            operatingCashFlow: $this->sumField($quarters, 'operating_cash_flow'),
            capex: $this->sumField($quarters, 'capex'),
            freeCashFlow: $this->sumField($quarters, 'free_cash_flow'),
            q1PeriodEnd: $quarters[0]['period_end_date'],
            q2PeriodEnd: $quarters[1]['period_end_date'],
            q3PeriodEnd: $quarters[2]['period_end_date'],
            q4PeriodEnd: $quarters[3]['period_end_date'],
            currency: $quarters[0]['currency'],
            calculatedAt: new DateTimeImmutable(),
        );

        $this->ttmQuery->upsert($ttm);

        return $ttm;
    }

    /**
     * @param list<array> $quarters
     */
    private function areConsecutive(array $quarters): bool
    {
        for ($i = 1; $i < count($quarters); $i++) {
            $prev = new DateTimeImmutable($quarters[$i - 1]['period_end_date']);
            $curr = new DateTimeImmutable($quarters[$i]['period_end_date']);
            $diffDays = $prev->diff($curr)->days;

            // Quarters should be ~90 days apart (allow 80-100 day range)
            if ($diffDays < 80 || $diffDays > 100) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param list<array> $quarters
     */
    private function sumField(array $quarters, string $field): ?float
    {
        $values = array_column($quarters, $field);
        $nonNull = array_filter($values, fn ($v) => $v !== null);

        if (count($nonNull) === 0) {
            return null;
        }

        return array_sum($nonNull);
    }
}
```

### Event-Driven TTM Updates

```php
<?php

declare(strict_types=1);

namespace app\handlers\dossier;

use app\events\QuarterlyFinancialsCollectedEvent;

/**
 * Listens for quarterly financials collection and triggers TTM recalculation.
 */
final class RecalculateTtmOnQuarterlyCollected
{
    public function __construct(
        private readonly TtmCalculator $calculator,
    ) {}

    public function handle(QuarterlyFinancialsCollectedEvent $event): void
    {
        $this->calculator->calculate(
            $event->companyId,
            $event->periodEndDate
        );
    }
}
```

---

### `valuation_snapshot`

Point-in-time valuation metrics. One per company per day (at most).

```sql
CREATE TABLE valuation_snapshot (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      BIGINT UNSIGNED NOT NULL,
    snapshot_date   DATE NOT NULL,

    -- Price & Market Data
    price           DECIMAL(12,4) NULL,
    market_cap      DECIMAL(20,2) NULL,
    enterprise_value DECIMAL(20,2) NULL,
    shares_outstanding BIGINT UNSIGNED NULL,

    -- Valuation Ratios
    trailing_pe     DECIMAL(10,4) NULL,
    forward_pe      DECIMAL(10,4) NULL,
    peg_ratio       DECIMAL(10,4) NULL,
    price_to_book   DECIMAL(10,4) NULL,
    price_to_sales  DECIMAL(10,4) NULL,
    ev_to_ebitda    DECIMAL(10,4) NULL,
    ev_to_revenue   DECIMAL(10,4) NULL,

    -- Yield Metrics
    dividend_yield  DECIMAL(8,4) NULL,          -- As decimal (0.0345 = 3.45%)
    fcf_yield       DECIMAL(8,4) NULL,
    earnings_yield  DECIMAL(8,4) NULL,

    -- Leverage
    net_debt_to_ebitda DECIMAL(10,4) NULL,

    -- Retention
    retention_tier  ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',

    -- Metadata
    currency        CHAR(3) NOT NULL,
    source_adapter  VARCHAR(50) NOT NULL,
    collected_at    DATETIME NOT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_date (company_id, snapshot_date),
    KEY idx_snapshot_date (snapshot_date),
    KEY idx_company_recent (company_id, snapshot_date DESC),
    KEY idx_retention (retention_tier, snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Market Data Tables

### `price_history`

Daily price data for stocks, commodities, indices.

```sql
CREATE TABLE price_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    symbol          VARCHAR(20) NOT NULL,        -- Ticker or symbol (XOM, CL=F, ^GSPC)
    symbol_type     ENUM('stock', 'commodity', 'index', 'etf', 'fx') NOT NULL,
    price_date      DATE NOT NULL,

    open            DECIMAL(12,4) NULL,
    high            DECIMAL(12,4) NULL,
    low             DECIMAL(12,4) NULL,
    close           DECIMAL(12,4) NOT NULL,
    adjusted_close  DECIMAL(12,4) NULL,
    volume          BIGINT UNSIGNED NULL,

    currency        CHAR(3) NOT NULL DEFAULT 'USD',
    source_adapter  VARCHAR(50) NOT NULL,
    collected_at    DATETIME NOT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_symbol_date (symbol, price_date),
    KEY idx_symbol_recent (symbol, price_date DESC),
    KEY idx_date (price_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `fx_rate`

Daily FX rates from ECB.

```sql
CREATE TABLE fx_rate (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    base_currency   CHAR(3) NOT NULL,            -- Always 'EUR' for ECB
    quote_currency  CHAR(3) NOT NULL,            -- 'USD', 'GBP', etc.
    rate_date       DATE NOT NULL,
    rate            DECIMAL(12,6) NOT NULL,      -- EUR/USD = 1.0892

    source_adapter  VARCHAR(50) NOT NULL DEFAULT 'ecb',
    collected_at    DATETIME NOT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_pair_date (base_currency, quote_currency, rate_date),
    KEY idx_quote_date (quote_currency, rate_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `macro_indicator`

Industry-specific macro data (rig counts, inventories, etc.).

```sql
CREATE TABLE macro_indicator (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    indicator_key   VARCHAR(100) NOT NULL,       -- 'rig_count', 'oil_inventory', etc.
    indicator_date  DATE NOT NULL,

    value           DECIMAL(20,4) NOT NULL,
    unit            VARCHAR(50) NOT NULL,        -- 'count', 'barrels', 'mmbtu'

    source_adapter  VARCHAR(50) NOT NULL,
    source_url      VARCHAR(500) NULL,
    collected_at    DATETIME NOT NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_indicator_date (indicator_key, indicator_date),
    KEY idx_indicator_recent (indicator_key, indicator_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Audit Tables

### `collection_attempt`

Audit log of all collection attempts.

```sql
CREATE TABLE collection_attempt (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- What we tried to collect
    entity_type     ENUM('company', 'price', 'fx', 'macro') NOT NULL,
    entity_id       BIGINT UNSIGNED NULL,        -- company_id if entity_type='company'
    data_type       VARCHAR(50) NOT NULL,        -- 'financials', 'quarters', 'valuation', 'price'

    -- Source details
    source_adapter  VARCHAR(50) NOT NULL,
    source_url      VARCHAR(500) NOT NULL,

    -- Outcome
    outcome         ENUM('success', 'not_found', 'rate_limited', 'blocked', 'error') NOT NULL,
    http_status     SMALLINT UNSIGNED NULL,
    error_message   VARCHAR(500) NULL,

    attempted_at    DATETIME NOT NULL,
    duration_ms     INT UNSIGNED NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_entity (entity_type, entity_id, attempted_at DESC),
    KEY idx_adapter_outcome (source_adapter, outcome, attempted_at),
    KEY idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `data_gap`

Tracks known missing data (explicit not_found vs never attempted).

```sql
CREATE TABLE data_gap (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id      BIGINT UNSIGNED NOT NULL,
    data_type       VARCHAR(50) NOT NULL,        -- 'annual_2022', 'q3_2023', 'valuation'

    gap_reason      ENUM('not_found', 'not_reported', 'private', 'delisted') NOT NULL,
    first_detected  DATETIME NOT NULL,
    last_checked    DATETIME NOT NULL,
    check_count     INT UNSIGNED NOT NULL DEFAULT 1,

    notes           VARCHAR(500) NULL,

    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
    UNIQUE KEY uk_company_gap (company_id, data_type),
    KEY idx_last_checked (last_checked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Currency Transparency

Store values in native currency. Conversion handled in PHP application layer for performance.

**Rationale:** SQL scalar functions with `ORDER BY ... LIMIT 1` cause N+1 query problems. Batch-loading FX rates in PHP is more efficient and testable.

### CurrencyConverter Service

```php
<?php

declare(strict_types=1);

namespace app\transformers;

use app\queries\FxRateQuery;
use DateTimeImmutable;

/**
 * Converts monetary values between currencies using ECB rates.
 *
 * Rates are batch-loaded and cached to avoid N+1 queries.
 */
final class CurrencyConverter
{
    /** @var array<string, array<string, float>> Cached rates by date+pair */
    private array $rateCache = [];

    public function __construct(
        private readonly FxRateQuery $fxRateQuery,
    ) {}

    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        DateTimeImmutable $asOfDate
    ): float {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getRate($fromCurrency, $toCurrency, $asOfDate);
        return round($amount * $rate, 2);
    }

    /**
     * Batch-convert multiple amounts for efficiency.
     *
     * @param list<array{amount: float, currency: string, date: DateTimeImmutable}> $items
     * @return list<float>
     */
    public function convertBatch(array $items, string $toCurrency): array
    {
        // Pre-load all needed rates in one query
        $this->preloadRates($items, $toCurrency);

        return array_map(
            fn (array $item): float => $this->convert(
                $item['amount'],
                $item['currency'],
                $toCurrency,
                $item['date']
            ),
            $items
        );
    }

    /**
     * Get FX rate, using EUR as intermediate for cross rates.
     */
    public function getRate(
        string $fromCurrency,
        string $toCurrency,
        DateTimeImmutable $asOfDate
    ): float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = $this->cacheKey($fromCurrency, $toCurrency, $asOfDate);
        if (isset($this->rateCache[$cacheKey])) {
            return $this->rateCache[$cacheKey];
        }

        // EUR is base currency in fx_rate table
        if ($fromCurrency === 'EUR') {
            $rate = $this->fxRateQuery->findClosestRate($toCurrency, $asOfDate) ?? 1.0;
        } elseif ($toCurrency === 'EUR') {
            $eurRate = $this->fxRateQuery->findClosestRate($fromCurrency, $asOfDate);
            $rate = $eurRate !== null ? 1 / $eurRate : 1.0;
        } else {
            // Cross rate via EUR: FROM -> EUR -> TO
            $fromEur = $this->fxRateQuery->findClosestRate($fromCurrency, $asOfDate) ?? 1.0;
            $toEur = $this->fxRateQuery->findClosestRate($toCurrency, $asOfDate) ?? 1.0;
            $rate = $toEur / $fromEur;
        }

        $this->rateCache[$cacheKey] = $rate;
        return $rate;
    }

    /**
     * Pre-load rates for a batch of items to avoid N+1 queries.
     *
     * @param list<array{amount: float, currency: string, date: DateTimeImmutable}> $items
     */
    private function preloadRates(array $items, string $toCurrency): void
    {
        $currencies = array_unique(array_column($items, 'currency'));
        $currencies = array_filter($currencies, fn (string $c): bool => $c !== $toCurrency);

        if (empty($currencies)) {
            return;
        }

        $minDate = min(array_map(fn ($i) => $i['date'], $items));
        $maxDate = max(array_map(fn ($i) => $i['date'], $items));

        // Batch load all rates in date range
        $rates = $this->fxRateQuery->findRatesInRange($currencies, $minDate, $maxDate);

        foreach ($rates as $rate) {
            $key = $this->cacheKey('EUR', $rate['quote_currency'], $rate['rate_date']);
            $this->rateCache[$key] = (float) $rate['rate'];
        }
    }

    private function cacheKey(string $from, string $to, DateTimeImmutable $date): string
    {
        return sprintf('%s_%s_%s', $from, $to, $date->format('Y-m-d'));
    }
}
```

### FxRateQuery

```php
<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Query class for fx_rate table.
 */
final class FxRateQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {}

    /**
     * Find the closest rate on or before the given date.
     */
    public function findClosestRate(string $quoteCurrency, DateTimeImmutable $asOfDate): ?float
    {
        $row = $this->db->createCommand(
            'SELECT rate FROM fx_rate
             WHERE quote_currency = :currency AND rate_date <= :date
             ORDER BY rate_date DESC LIMIT 1'
        )
            ->bindValues([':currency' => $quoteCurrency, ':date' => $asOfDate->format('Y-m-d')])
            ->queryOne();

        return $row !== false ? (float) $row['rate'] : null;
    }

    /**
     * Batch load rates for multiple currencies in a date range.
     *
     * @param list<string> $currencies
     * @return list<array{quote_currency: string, rate_date: DateTimeImmutable, rate: float}>
     */
    public function findRatesInRange(
        array $currencies,
        DateTimeImmutable $minDate,
        DateTimeImmutable $maxDate
    ): array {
        if (empty($currencies)) {
            return [];
        }

        return $this->db->createCommand(
            'SELECT quote_currency, rate_date, rate FROM fx_rate
             WHERE quote_currency IN (:currencies)
               AND rate_date BETWEEN :minDate AND :maxDate
             ORDER BY quote_currency, rate_date'
        )
            ->bindValues([
                ':currencies' => $currencies,
                ':minDate' => $minDate->format('Y-m-d'),
                ':maxDate' => $maxDate->format('Y-m-d'),
            ])
            ->queryAll();
    }
}
```

---

## Staleness Detection

### Rules by Data Type

| Data Type | Stale When | Check Frequency |
|-----------|------------|-----------------|
| Annual Financials | Fiscal year ended 90+ days ago AND we don't have it | Weekly |
| Quarterly Financials | Quarter ended 45+ days ago AND we don't have it | Weekly |
| Valuation | Older than 1 trading day | Daily |
| Stock Price | Missing today (if market open) | Daily |
| FX Rates | Missing today | Daily |
| Macro (rig count) | Missing this week's Friday | Weekly |
| Macro (inventory) | Missing this week's Wednesday | Weekly |

### Example Query: Companies Needing Financial Updates

```sql
SELECT c.id, c.ticker, c.fiscal_year_end,
       MAX(af.fiscal_year) AS latest_year,
       YEAR(CURDATE()) - COALESCE(MAX(af.fiscal_year), 0) AS years_behind
FROM company c
LEFT JOIN annual_financial af ON af.company_id = c.id AND af.is_current = TRUE
WHERE c.financials_collected_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
   OR c.financials_collected_at IS NULL
GROUP BY c.id
HAVING years_behind > 0
   AND DATEDIFF(CURDATE(),
       DATE(CONCAT(COALESCE(MAX(af.fiscal_year), YEAR(CURDATE())-1), '-',
            LPAD(c.fiscal_year_end, 2, '0'), '-01'))) > 90;
```

---

## Versioning Strategy

When source data is corrected (e.g., FMP restates a historical figure):

1. Set `is_current = FALSE` on existing row
2. Insert new row with `version = version + 1` and `is_current = TRUE`
3. Both versions remain for audit trail

```sql
-- Example: Correcting 2022 revenue for XOM
UPDATE annual_financial
SET is_current = FALSE
WHERE company_id = 123 AND fiscal_year = 2022 AND is_current = TRUE;

INSERT INTO annual_financial (company_id, fiscal_year, period_end_date, revenue, ..., version, is_current)
SELECT company_id, fiscal_year, period_end_date, 413680000000.00, ..., version + 1, TRUE
FROM annual_financial
WHERE company_id = 123 AND fiscal_year = 2022
ORDER BY version DESC LIMIT 1;
```

---

## Valuation Snapshot Retention

Compress historical snapshots to reduce storage while preserving trend data.

### Retention Policy

| Age | Granularity | Keep |
|-----|-------------|------|
| 0-30 days | Daily | All |
| 31-365 days | Weekly | Friday close only |
| 1+ years | Monthly | Last trading day |

### Compression Command

The compression logic is implemented in PHP for testability and maintainability.

```bash
# Run compression
docker exec aimm_yii php yii compress-valuation

# Dry run (preview changes without applying)
docker exec aimm_yii php yii compress-valuation --dryRun
```

**Implementation:** `yii/src/commands/CompressValuationController.php`

```php
<?php

declare(strict_types=1);

namespace app\commands;

use DateTimeImmutable;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;

/**
 * Compresses valuation snapshots according to retention policy.
 *
 * Retention tiers:
 * - Daily (0-30 days): Keep all snapshots
 * - Weekly (31-365 days): Keep only Friday snapshots
 * - Monthly (1+ years): Keep only month-end snapshots
 */
final class CompressValuationController extends Controller
{
    private const DAILY_RETENTION_DAYS = 30;
    private const WEEKLY_RETENTION_DAYS = 365;

    public bool $dryRun = false;

    public function actionIndex(): int
    {
        $today = new DateTimeImmutable('today');
        $weeklyCutoff = $today->modify('-30 days');
        $monthlyCutoff = $today->modify('-365 days');

        $transaction = $this->db->beginTransaction();

        try {
            // Step 1: Weekly tier - promote Fridays, delete non-Fridays
            $this->promoteToWeeklyTier($weeklyCutoff, $monthlyCutoff);
            $this->deleteNonFridayDailies($weeklyCutoff, $monthlyCutoff);

            // Step 2: Monthly tier - promote month-end, delete non-month-end
            $this->promoteToMonthlyTier($monthlyCutoff);
            $this->deleteNonMonthEndWeeklies($monthlyCutoff);

            $this->dryRun ? $transaction->rollBack() : $transaction->commit();

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
```

### Scheduled Execution

Schedule via cron (recommended) or system scheduler:

```bash
# /etc/cron.d/aimm-valuation-compress
0 3 * * * root docker exec aimm_yii php yii compress-valuation >> /var/log/aimm/compress.log 2>&1
```

---

## Migration Path

### Phase 1: Schema Creation

Create all tables via Yii migration:

```bash
docker exec aimm_yii php yii migrate/create create_dossier_schema
```

### Phase 2: Backfill from Existing Datapacks

Parse existing Phase 1 datapack JSON files and populate the dossier tables.

**Datapack JSON Structure (Phase 1):**
```json
{
  "industry_id": "oil_majors",
  "collected_at": "2024-01-15T12:00:00Z",
  "companies": [
    {
      "ticker": "XOM",
      "company_data": {
        "valuation": {
          "market_cap": {"value": 420000000000, "currency": "USD", "source": "yahoo"},
          "trailing_pe": {"value": 11.86, "source": "yahoo"}
        },
        "financials": {
          "FY2023": {"revenue": 413680000000, "ebitda": 72000000000, ...},
          "FY2022": {...}
        },
        "quarters": {
          "Q4_2023": {"revenue": 84000000000, ...},
          "Q3_2023": {...}
        }
      }
    }
  ]
}
```

**Backfill Script:**

```php
<?php

declare(strict_types=1);

namespace app\commands;

use app\queries\CompanyQuery;
use app\queries\AnnualFinancialQuery;
use app\queries\QuarterlyFinancialQuery;
use app\queries\ValuationSnapshotQuery;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * One-time migration script to backfill dossier from Phase 1 datapacks.
 */
final class BackfillDossierController extends Controller
{
    private const DATAPACK_DIR = '@runtime/datapacks';

    public function __construct(
        $id,
        $module,
        private readonly CompanyQuery $companyQuery,
        private readonly AnnualFinancialQuery $annualQuery,
        private readonly QuarterlyFinancialQuery $quarterlyQuery,
        private readonly ValuationSnapshotQuery $valuationQuery,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): int
    {
        $datapackFiles = glob(\Yii::getAlias(self::DATAPACK_DIR) . '/*.json');

        foreach ($datapackFiles as $file) {
            $this->stdout("Processing: {$file}\n");
            $this->processDatapack($file);
        }

        return ExitCode::OK;
    }

    private function processDatapack(string $filePath): void
    {
        $json = json_decode(file_get_contents($filePath), true);
        $collectedAt = new \DateTimeImmutable($json['collected_at']);

        foreach ($json['companies'] as $companyData) {
            $ticker = $companyData['ticker'];

            // Ensure company exists
            $companyId = $this->companyQuery->findOrCreate($ticker);

            // Import financials (skip if already exists)
            $this->importFinancials($companyId, $companyData['company_data']['financials'] ?? [], $collectedAt);

            // Import quarters
            $this->importQuarters($companyId, $companyData['company_data']['quarters'] ?? [], $collectedAt);

            // Import valuation snapshot
            $this->importValuation($companyId, $companyData['company_data']['valuation'] ?? [], $collectedAt);
        }
    }

    private function importFinancials(int $companyId, array $financials, \DateTimeImmutable $collectedAt): void
    {
        foreach ($financials as $periodKey => $data) {
            // Parse "FY2023" -> fiscal_year = 2023
            if (!preg_match('/^FY(\d{4})$/', $periodKey, $matches)) {
                continue;
            }

            $fiscalYear = (int) $matches[1];

            // Skip if already exists
            if ($this->annualQuery->exists($companyId, $fiscalYear)) {
                continue;
            }

            $this->annualQuery->insert([
                'company_id' => $companyId,
                'fiscal_year' => $fiscalYear,
                'period_end_date' => "{$fiscalYear}-12-31", // Default, can be refined
                'revenue' => $data['revenue'] ?? null,
                'ebitda' => $data['ebitda'] ?? null,
                'net_income' => $data['net_income'] ?? null,
                'free_cash_flow' => $data['free_cash_flow'] ?? null,
                'currency' => $data['currency'] ?? 'USD',
                'source_adapter' => $data['source'] ?? 'datapack_import',
                'collected_at' => $collectedAt->format('Y-m-d H:i:s'),
            ]);
        }
    }

    // ... similar methods for importQuarters() and importValuation()
}
```

### Phase 3: Update Collectors

Modify collection handlers to write to dossier instead of datapack files:

1. `CollectCompanyHandler` → writes to `annual_financial`, `quarterly_financial`, `valuation_snapshot`
2. `CollectMacroHandler` → writes to `macro_indicator`, `price_history`
3. Remove `DataPackAssembler` usage

### Phase 4: Update Analysis

Modify analysis to read from dossier:

1. Create `DossierQuery` classes for each table
2. Update `AnalysisHandler` to use queries instead of datapack reader
3. Integrate `CurrencyConverter` for multi-currency peer comparisons

### Phase 5: Deprecate Datapacks

1. Remove datapack generation code
2. Archive existing datapack files
3. Remove `DataPackAssembler`, `DataPackReader` classes

### Phase 6: Cleanup

1. Remove unused datapack-related code
2. Update documentation
3. Run full regression tests
