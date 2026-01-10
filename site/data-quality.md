# Data Quality

Every datapoint in AIMM must carry full provenance. This page explains the data quality requirements and validation mechanisms.

## Datapoint Provenance

Every collected value must include:

```json
{
  "value": 12.5,
  "unit": "ratio",
  "as_of": "2025-12-10",
  "source_url": "https://finance.yahoo.com/quote/SHEL",
  "retrieved_at": "2025-12-13T10:30:00Z",
  "method": "web_fetch",
  "source_locator": {
    "type": "html",
    "selector": "td[data-test='FORWARD_PE-value']",
    "snippet": "Forward P/E: 12.5"
  }
}
```

### Required Fields

| Field | Description |
|-------|-------------|
| `value` | The actual data value (or `null` if not found) |
| `as_of` | The date the value represents |
| `retrieved_at` | When the value was collected (ISO 8601) |
| `source_url` | Where the value came from |
| `method` | How it was collected (`web_fetch`, `web_search`, `api`, `cache`, `derived`, `not_found`) |
| `source_locator` | How to find the value in the source |

## Typed Datapoints

| Type | Use Case | Key Fields |
|------|----------|------------|
| `DataPointNumber` | Generic numbers (production volumes) | `value`, `unit` |
| `DataPointMoney` | Monetary values | `value`, `currency`, `scale`, `fx_conversion` |
| `DataPointPercent` | Percentages (yields, margins) | `value` (stored as 4.5 for 4.5%) |
| `DataPointRatio` | Dimensionless ratios (P/E, EV/EBITDA) | `value` (stored as 12.5 for 12.5x) |

Additional provenance fields are required for specific methods:
- `derived`: `derived_from`, `formula`
- `cache`: `cache_source`, `cache_age_days`

## Nullable vs Required

### Required Datapoint

Must have a value. Collection fails if missing.

### Nullable Datapoint

May have `null` value, but must record `attempted_sources`:

```json
{
  "value": null,
  "as_of": "2025-12-13",
  "retrieved_at": "2025-12-13T10:30:00Z",
  "method": "not_found",
  "attempted_sources": [
    "https://finance.yahoo.com/quote/SHEL",
    "https://www.reuters.com/companies/SHEL.L"
  ]
}
```

::: warning Important
Never fabricate data. Document gaps as `not_found` with attempted sources.
:::

## Validation Gates

Gates are checkpoints that prevent bad data from flowing downstream.

### Collection Gate (after Phase 1)

| Check | Description |
|-------|-------------|
| Company status | Fail on `failed`, warn on `partial` |
| Macro status | Fail on `failed`, warn on `partial` |
| Company coverage | Missing configured tickers are warnings |

### Analysis Gate (after Phase 2)

| Check | Description |
|-------|-------------|
| Minimum companies | At least 2 companies required |
| Analyzable companies | At least 2 companies with >= 2 years + market cap |
| Data freshness | Warn when data is older than 30 days |

## Error Handling

### GateResult Structure

```php
class GateResult
{
    public function __construct(
        public bool $passed,
        public array $errors,    // Fatal issues
        public array $warnings,  // Non-fatal issues
    ) {}
}
```

- **Errors**: Stop the pipeline. Must be fixed before proceeding.
- **Warnings**: Log and continue. Review recommended.

### Exit Codes

| Code | Constant | Meaning |
|------|----------|---------|
| 0 | `ExitCode::OK` | Success |
| 65 | `ExitCode::DATAERR` | Data/validation error (gate failed) |
| 1 | `ExitCode::UNSPECIFIED_ERROR` | Internal error (exception) |
