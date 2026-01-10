# Configuration

AIMM is database-driven. Industry configuration is stored in the database and combined with
collection policy settings at runtime. Application parameters live in `yii/config/params.php`.

## Industry Configuration (DB)

Industry configuration is assembled from these tables:

- `sector`
- `industry`
- `company`
- `collection_policy`

The `industry.slug` value is the CLI identifier (used by `yii collect/industry <slug>` and
`yii analyze/industry <slug>`).

## Collection Policy Payload

Policies store JSON arrays in DB columns (valuation, annual, quarterly, operational metrics,
and indicators). Example JSON payload for a policy export:

```json
{
  "name": "Default Policy",
  "description": "Baseline requirements",
  "history_years": 5,
  "quarters_to_fetch": 8,
  "valuation_metrics": [
    { "key": "market_cap", "unit": "currency", "required": true, "required_scope": "all" },
    { "key": "fwd_pe", "unit": "ratio", "required": true, "required_scope": "all" }
  ],
  "annual_financial_metrics": [],
  "quarterly_financial_metrics": [],
  "operational_metrics": [],
  "commodity_benchmark": "BRENT",
  "margin_proxy": "CRACK_3_2_1",
  "sector_index": "XLE",
  "required_indicators": [],
  "optional_indicators": [],
  "analysis_thresholds": {
    "buy_gap_threshold": 15,
    "fair_value_threshold": 5,
    "min_metrics_for_gap": 2
  }
}
```

## JSON Schemas

Only `industry-datapack.schema.json` is present under `yii/config/schemas/`.
It is used by `CollectionGateValidator` when validating a datapack object.

## Application Parameters

Located in `yii/config/params.php`:

```php
return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
    'gotenbergBaseUrl' => getenv('GOTENBERG_BASE_URL') ?: 'http://aimm_gotenberg:3000',
    'allowedSourceDomains' => [
        'financialmodelingprep.com',
        'finance.yahoo.com',
        'query1.finance.yahoo.com',
        'www.reuters.com',
        'www.wsj.com',
        'www.bloomberg.com',
        'www.morningstar.com',
        'seekingalpha.com',
        'stockanalysis.com',
        'rigcount.bakerhughes.com',
        'api.eia.gov',
        'www.ecb.europa.eu',
    ],
    'eiaApiKey' => 'DEMO_KEY',
    'fmpApiKey' => getenv('FMP_API_KEY') ?: null,
    'rateLimiter' => 'file',
];
```
