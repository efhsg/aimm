# Collection User Guide

This guide explains how to use the Phase 1 collection system to gather financial data for an industry.

## Overview

The collection system fetches financial data for configured companies from multiple sources (Yahoo Finance, StockAnalysis, Reuters, etc.), validates the data, and produces a **datapack** — a JSON file containing all collected metrics with full provenance.

## Prerequisites

1. **Database**: Migrations must be applied
   ```bash
   docker exec aimm_yii php yii migrate --interactive=0
   ```

2. **Configuration**: At least one industry must be configured in the `industry_config` table

   Minimal insert example (replace JSON with your config):
   ```sql
   INSERT INTO industry_config (industry_id, name, config_json, is_active)
   VALUES (
     'oil-majors',
     'Oil Majors',
     '{...}',
     1
   );
   ```

## Quick Start

```bash
# Collect data for an industry
docker exec aimm_yii php yii collect/industry oil-majors
```

Output:
```
+------------+--------------------------------------+----------+----------+
| Industry   | Datapack ID                          | Status   | Duration |
+------------+--------------------------------------+----------+----------+
| oil-majors | a1b2c3d4-e5f6-7890-abcd-ef1234567890 | complete | 45.23s   |
+------------+--------------------------------------+----------+----------+
```

## Configuring an Industry

Industries are stored in the `industry_config` table with JSON configuration.

### Database Schema

| Column | Type | Description |
|--------|------|-------------|
| `industry_id` | VARCHAR(64) | Unique identifier (e.g., `oil-majors`) |
| `name` | VARCHAR(255) | Human-readable name |
| `config_json` | TEXT | JSON configuration (see below) |
| `is_active` | BOOLEAN | Whether collection is enabled for batch runs (single-industry CLI ignores this flag) |

### Configuration Structure

**Schema**: `yii/config/schemas/industry-config.schema.json` (invalid JSON or schema violations fail collection before it starts).

**Important**: `config_json.id` must match `industry_config.industry_id` to keep datapack paths and run logs aligned.

```json
{
  "id": "oil-majors",
  "name": "Oil Majors",
  "sector": "Energy",
  "companies": [
    {
      "ticker": "SHEL",
      "name": "Shell plc",
      "listing_exchange": "NYSE",
      "listing_currency": "USD",
      "reporting_currency": "USD",
      "fy_end_month": 12,
      "alternative_tickers": ["SHEL.L", "RDSA"]
    },
    {
      "ticker": "XOM",
      "name": "Exxon Mobil Corporation",
      "listing_exchange": "NYSE",
      "listing_currency": "USD",
      "reporting_currency": "USD",
      "fy_end_month": 12,
      "alternative_tickers": null
    }
  ],
  "macro_requirements": {
    "commodity_benchmark": "BRENT",
    "margin_proxy": null,
    "sector_index": "XLE",
    "required_indicators": ["rig_count"],
    "optional_indicators": ["inventory"]
  },
  "data_requirements": {
    "history_years": 5,
    "quarters_to_fetch": 8,
    "valuation_metrics": [
      {"key": "market_cap", "unit": "currency", "required": true},
      {"key": "fwd_pe", "unit": "ratio", "required": false},
      {"key": "trailing_pe", "unit": "ratio", "required": false},
      {"key": "ev_ebitda", "unit": "ratio", "required": false},
      {"key": "fcf_yield", "unit": "percent", "required": false},
      {"key": "div_yield", "unit": "percent", "required": false}
    ],
    "annual_financial_metrics": [],
    "quarter_metrics": [],
    "operational_metrics": []
  }
}
```

### Configuration Reference

#### Company Config

| Field | Required | Description |
|-------|----------|-------------|
| `ticker` | Yes | Stock ticker symbol (uppercase, e.g., `SHEL`) |
| `name` | Yes | Company name |
| `listing_exchange` | Yes | Primary exchange (e.g., `NYSE`, `LSE`) |
| `listing_currency` | Yes | Currency of stock price (ISO 4217, e.g., `USD`) |
| `reporting_currency` | Yes | Currency of financial statements (ISO 4217) |
| `fy_end_month` | Yes | Fiscal year end month (1-12) |
| `alternative_tickers` | No | Array of alternative ticker symbols |

#### Macro Requirements

| Field | Description |
|-------|-------------|
| `commodity_benchmark` | Commodity price to track (e.g., `BRENT`, `WTI`, `GOLD`) |
| `margin_proxy` | Margin/spread indicator |
| `sector_index` | Sector ETF or index (e.g., `XLE`, `XLF`) |
| `required_indicators` | Array of required macro indicators |
| `optional_indicators` | Array of optional macro indicators |

### Macro Sources

- `commodity_benchmark`, `margin_proxy`, `sector_index`: Yahoo Finance quote data
- `rig_count`: Baker Hughes North America rig count XLSX
- `inventory`: U.S. crude oil inventories via EIA API (series `PET.WCRSTUS1.W`)

Configure source credentials/URLs in `yii/config/params-local.php`:

```php
return [
    'rigCountXlsxUrl' => 'https://rigcount.bakerhughes.com/static-files/<latest>.xlsx',
    'eiaApiKey' => 'DEMO_KEY',
    'eiaInventorySeriesId' => 'PET.WCRSTUS1.W',
];
```

#### Data Requirements

| Field | Description |
|-------|-------------|
| `history_years` | Years of annual financial history (0-20) |
| `quarters_to_fetch` | Number of quarters to collect (0-20) |
| `valuation_metrics` | Array of valuation metrics to collect |
| `annual_financial_metrics` | Array of annual metrics |
| `quarter_metrics` | Array of quarterly metrics |
| `operational_metrics` | Array of operational KPIs |

#### Metric Definition

| Field | Description |
|-------|-------------|
| `key` | Metric identifier (snake_case) |
| `unit` | One of: `currency`, `ratio`, `percent`, `number` |
| `required` | If `true`, collection fails when metric is missing |

### Available Valuation Metrics

| Key | Unit | Description |
|-----|------|-------------|
| `market_cap` | currency | Market capitalization |
| `fwd_pe` | ratio | Forward P/E ratio |
| `trailing_pe` | ratio | Trailing P/E ratio |
| `ev_ebitda` | ratio | EV/EBITDA multiple |
| `free_cash_flow_ttm` | currency | Trailing 12-month free cash flow |
| `fcf_yield` | percent | Free cash flow yield |
| `div_yield` | percent | Dividend yield |
| `net_debt_ebitda` | ratio | Net debt to EBITDA |
| `price_to_book` | ratio | Price to book value |

## Running Collection

### Command Syntax

```bash
docker exec aimm_yii php yii collect/industry <industry_id>
```

### Exit Codes and Status

| Exit Code | Status | Description |
|-----------|--------|-------------|
| 0 | `complete` | No failed/partial companies, macro not failed, and gate passed |
| 1 | `partial` | Macro not failed, gate passed, but at least one company failed/partial |
| 1 | `failed` | Gate failed, macro failed, or more than 50% of companies failed |
| 65 | — | Industry config not found in database |

**Note:** Only `complete` status returns exit code 0. Both `partial` and `failed` return exit code 1. Gate failures override any prior status and force `failed`.

## Output: The Datapack

Datapacks are saved to:
```
runtime/datapacks/{industry_id}/{datapack_id}/datapack.json
```

### Datapack Structure

```json
{
  "industry_id": "oil-majors",
  "datapack_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "collected_at": "2024-12-29T10:30:00+00:00",
  "macro": {
    "commodity_benchmark": { ... },
    "sector_index": { ... },
    "additional_indicators": { ... }
  },
  "companies": {
    "SHEL": {
      "ticker": "SHEL",
      "name": "Shell plc",
      "listing_exchange": "NYSE",
      "listing_currency": "USD",
      "reporting_currency": "USD",
      "valuation": {
        "market_cap": { ... },
        "fwd_pe": { ... }
      },
      "financials": { ... },
      "quarters": { ... }
    }
  },
  "collection_log": {
    "started_at": "2024-12-29T10:29:15+00:00",
    "completed_at": "2024-12-29T10:30:00+00:00",
    "duration_seconds": 45,
    "company_statuses": {
      "SHEL": "complete",
      "XOM": "partial"
    },
    "macro_status": "complete",
    "total_attempts": 24
  }
}
```

### DataPoint Structure

Every data value includes provenance:

```json
{
  "value": 185.5,
  "unit": "currency",
  "currency": "USD",
  "scale": "billions",
  "as_of": "2024-12-29",
  "source_url": "https://finance.yahoo.com/quote/SHEL",
  "retrieved_at": "2024-12-29T10:29:20+00:00",
  "method": "web_fetch",
  "source_locator": {
    "type": "json",
    "selector": "$.quoteSummary.result[0].price.marketCap.raw",
    "snippet": "185500000000"
  }
}
```

#### Collection Methods

| Method | Description |
|--------|-------------|
| `web_fetch` | Scraped from web page |
| `web_search` | Found via web search |
| `api` | Retrieved from API |
| `cache` | Taken from the latest datapack for the same industry (max 7 days old, valuation metrics only) |
| `derived` | Calculated from other datapoints |
| `not_found` | Could not be retrieved |

#### Scale Values (for currency)

| Scale | Multiplier |
|-------|------------|
| `units` | 1 |
| `thousands` | 1,000 |
| `millions` | 1,000,000 |
| `billions` | 1,000,000,000 |
| `trillions` | 1,000,000,000,000 |

## Monitoring Collection Runs

Collection history is stored in the `collection_run` table:

```sql
SELECT
    industry_id,
    datapack_id,
    status,
    started_at,
    duration_seconds,
    companies_success,
    companies_failed,
    gate_passed
FROM collection_run
WHERE industry_id = 'oil-majors'
ORDER BY started_at DESC
LIMIT 10;
```

### Viewing Errors

```sql
SELECT
    ce.severity,
    ce.error_code,
    ce.error_message,
    ce.ticker
FROM collection_error ce
JOIN collection_run cr ON cr.id = ce.collection_run_id
WHERE cr.datapack_id = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
```

## Troubleshooting

### "Industry config not found"

The industry ID doesn't exist in the `industry_config` table.

```sql
SELECT industry_id, is_active FROM industry_config;
```

**Note:** The `is_active` flag only affects batch operations (`findAllActive`). Single-industry collection by ID works regardless of this flag.

### Collection Fails with Gate Errors

Check the `collection_error` table for details. Common issues:
- **Required metric missing**: Source doesn't have the data
- **Rate limiting**: Too many requests to a source
- **Stale macro data**: Macro datapoint retrieved_at older than the configured threshold (gate error)
- **Stale valuation/financial dates**: as_of dates too old (warnings)

Macro staleness uses `macroStalenessThresholdDays` from `yii/config/params.php` (override in `yii/config/params-local.php`).

### Rate Limiting / Blocked Sources

Sources that return 403/429 are temporarily blocked. Check:

```sql
SELECT domain, blocked_until, consecutive_count
FROM source_block
WHERE blocked_until > NOW();
```

Blocks expire automatically. For persistent blocks, check if the source requires authentication or has changed their anti-bot measures.

### Slow Collection

Collection speed depends on:
- Number of companies
- Number of metrics
- Rate limit delays between requests

Typical timing: 1-2 seconds per company for basic valuation metrics.

## Logs

Collection logs are written to `runtime/logs/collection.log`. The log target sanitizes sensitive data (API keys, tokens, etc.).

View recent collection logs:
```bash
tail -100 runtime/logs/collection.log
```

## Alerts

Critical failures (blocked sources, gate failures) trigger alerts via:
- Slack webhook (if configured)
- Email (if configured)

Configure in `yii/config/params-local.php` (create if it doesn't exist):
```php
<?php
return [
    'alerts' => [
        'slack_webhook' => 'https://hooks.slack.com/services/...',
        'email' => 'alerts@example.com',
        'from_email' => 'aimm@example.com',
    ],
];
```
`params-local.php` is merged over `params.php` for both console and web apps.

## Best Practices

1. **Start small**: Begin with 1-2 companies to verify configuration
2. **Use optional metrics**: Only mark truly essential metrics as `required`
3. **Schedule off-peak**: Run collection during market close to get stable data
4. **Monitor runs**: Check `collection_run` table for trends in success rates
5. **Review provenance**: Periodically audit datapoints to ensure sources are still valid
