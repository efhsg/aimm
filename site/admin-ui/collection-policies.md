# Collection Policies

Collection Policies define data requirements and macro inputs for industries.

## Index View

### Features

- List all policies
- View details or edit a policy

### Columns

| Column | Description |
|--------|-------------|
| Name | Policy display name |
| Slug | URL-safe identifier |
| History | Years of annual data |
| Quarters | Quarters to fetch |
| Created | Timestamp and user |
| Actions | View, Edit |

## Detail View

### Policy Metadata

| Field | Description |
|-------|-------------|
| Slug | Unique identifier |
| Description | Optional notes |
| History Years | Years of annual data |
| Quarters to Fetch | Number of recent quarters |
| Created | Timestamp and user |
| Updated | Timestamp |

### Macro Requirements

| Field | Description |
|-------|-------------|
| Commodity Benchmark | e.g., BRENT |
| Margin Proxy | e.g., CRACK_3_2_1 |
| Sector Index | e.g., XLE |
| Required Indicators | JSON array of keys |
| Optional Indicators | JSON array of keys |

### Data Requirements

Metrics are stored as JSON arrays with:

- `key` (string)
- `unit` (`currency`, `ratio`, `percent`, `number`)
- `required` (bool)
- `required_scope` (default `all`)

Example:

```json
[
  { "key": "market_cap", "unit": "currency", "required": true, "required_scope": "all" },
  { "key": "fwd_pe", "unit": "ratio", "required": true, "required_scope": "all" }
]
```

## Create/Update

### Core Fields

| Field | Description |
|-------|-------------|
| Name | Policy display name |
| Slug | URL-safe identifier |
| Description | Purpose and notes |

### Numeric Requirements

| Field | Description | Default |
|-------|-------------|---------|
| History Years | Years of annual data | 5 |
| Quarters to Fetch | Recent quarters | 8 |

### Validation Rules

- Slug is required and must be unique
- Name is required
- JSON fields must be valid JSON
