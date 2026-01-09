# Configuration

AIMM is driven by JSON configuration files and application parameters.

## Industry Config

Each industry has a JSON config file at `config/industries/{industry_id}.json`.

### Structure

```json
{
  "id": "integrated_oil_gas",
  "name": "Integrated Oil & Gas",
  "sector": "Energy",
  "companies": [
    {
      "ticker": "SHEL",
      "name": "Shell plc",
      "listing_exchange": "NYSE",
      "listing_currency": "USD",
      "reporting_currency": "USD",
      "fy_end_month": 12
    }
  ],
  "macro_requirements": {
    "commodity_benchmark": "BRENT",
    "margin_proxy": "CRACK_3_2_1",
    "sector_index": "XLE",
    "required_indicators": [],
    "optional_indicators": []
  },
  "data_requirements": {
    "history_years": 5,
    "quarters_to_fetch": 4,
    "valuation_metrics": [...],
    "annual_financial_metrics": [...],
    "quarter_metrics": [...],
    "operational_metrics": []
  }
}
```

### Key Fields

| Field | Description |
|-------|-------------|
| `id` | Machine identifier, used in CLI and file paths |
| `name` | Human-readable industry name |
| `sector` | Classification label for grouping |
| `companies` | List of companies to collect data for |
| `macro_requirements` | Industry-wide benchmarks and indicators |
| `data_requirements` | What metrics to collect and how much history |

### Company Fields

| Field | Description |
|-------|-------------|
| `ticker` | Stock symbol |
| `name` | Company name |
| `listing_exchange` | Primary exchange |
| `listing_currency` | Currency of stock price |
| `reporting_currency` | Currency of financials |
| `fy_end_month` | Fiscal year end month (1-12) |

### Data Requirements

```json
{
  "data_requirements": {
    "history_years": 5,
    "quarters_to_fetch": 4,
    "valuation_metrics": [
      { "key": "market_cap", "unit": "currency", "required": true },
      { "key": "fwd_pe", "unit": "ratio", "required": true },
      { "key": "ev_ebitda", "unit": "ratio", "required": true },
      { "key": "fcf_yield", "unit": "percent", "required": false }
    ],
    "annual_financial_metrics": [
      { "key": "revenue", "unit": "currency", "required": false },
      { "key": "ebitda", "unit": "currency", "required": false }
    ],
    "quarter_metrics": [
      { "key": "revenue", "unit": "currency", "required": false }
    ],
    "operational_metrics": []
  }
}
```

## JSON Schemas

AIMM uses JSON Schema draft-07 for validation.

| Schema | Purpose |
|--------|---------|
| `industry-config.schema.json` | Validates industry config files |
| `industry-datapack.schema.json` | Validates Phase 1 output |
| `report-dto.schema.json` | Validates Phase 2 output |

### Schema Rules

- `additionalProperties: false` on all objects
- Explicit `required` arrays
- Typed datapoints (not generic `value: any`)

## Application Parameters

Located in `config/params.php`:

```php
return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'gotenbergBaseUrl' => getenv('GOTENBERG_BASE_URL') ?: 'http://aimm_gotenberg:3000',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
];
```

| Parameter | Description |
|-----------|-------------|
| `schemaPath` | Location of JSON Schema files |
| `industriesPath` | Location of industry config files |
| `datapacksPath` | Output location for datapacks |
| `gotenbergBaseUrl` | Base URL for Gotenberg |
| `macroStalenessThresholdDays` | Max age for macro data (Collection Gate) |
| `renderTimeoutSeconds` | Timeout for PDF rendering subprocess |
