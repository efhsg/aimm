# Collection Policies

Collection Policies define the data requirements that drive collection.

## Overview

A Policy specifies:
- **Macro requirements** (commodity benchmarks, indices)
- **Data requirements** (which metrics to collect)
- **History depth** (how many years/quarters)

## Index View

### Features

- List of all policies
- Filter by sector
- Show sector defaults

### Columns

| Column | Description |
|--------|-------------|
| Name | Policy display name |
| Sector | Target sector (or "Default") |
| Description | Brief summary |
| Peer Groups | Count of linked groups |
| Actions | View, Edit |

## Detail View

### Policy Metadata

| Field | Description |
|-------|-------------|
| Name | Policy display name |
| Sector | Target sector |
| Description | Purpose and notes |
| Is Default | Whether this is the sector default |
| Created | Creation timestamp and user |
| Updated | Last update timestamp and user |

### Macro Requirements

| Field | Description |
|-------|-------------|
| Commodity Benchmark | e.g., BRENT, WTI, GOLD |
| Margin Proxy | e.g., CRACK_3_2_1 |
| Sector Index | e.g., XLE, XLF |
| Required Indicators | Must-have macro metrics |
| Optional Indicators | Nice-to-have macro metrics |

### Data Requirements

#### History Settings

| Field | Description |
|-------|-------------|
| History Years | Years of annual data to collect |
| Quarters to Fetch | Number of recent quarters |

#### Valuation Metrics

| Metric | Unit | Required |
|--------|------|----------|
| market_cap | currency | Yes |
| fwd_pe | ratio | Yes |
| ev_ebitda | ratio | Yes |
| trailing_pe | ratio | No |
| fcf_yield | percent | No |
| div_yield | percent | No |

#### Annual Financial Metrics

| Metric | Unit | Required |
|--------|------|----------|
| revenue | currency | No |
| ebitda | currency | No |
| net_income | currency | No |
| net_debt | currency | No |
| free_cash_flow | currency | No |

#### Quarter Metrics

| Metric | Unit | Required |
|--------|------|----------|
| revenue | currency | No |
| ebitda | currency | No |
| net_income | currency | No |

## Create/Update

### Core Fields

| Field | Description |
|-------|-------------|
| Name | Policy display name |
| Sector | Target sector |
| Description | Purpose and notes |
| Is Default | Set as sector default |

### Numeric Requirements

| Field | Description | Default |
|-------|-------------|---------|
| History Years | Years of annual data | 5 |
| Quarters to Fetch | Recent quarters | 4 |

### Metric Lists

Metrics are defined as JSON arrays:

```json
{
  "valuation_metrics": [
    { "key": "market_cap", "unit": "currency", "required": true },
    { "key": "fwd_pe", "unit": "ratio", "required": true }
  ]
}
```

### Validation Rules

- Name must be unique
- At least one required valuation metric
- History years must be 1-10
- Quarters to fetch must be 1-8

## Sector Defaults

Each sector can have one default policy:

1. When creating a new Peer Group, the sector default is pre-selected
2. Mark a policy as "Is Default" to make it the sector default
3. Only one policy per sector can be the default

::: tip
Create sector defaults first, then create specialized policies for specific peer groups.
:::
