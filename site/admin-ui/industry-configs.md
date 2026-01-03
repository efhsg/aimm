# Industry Configs

Industry Configs define the industry-level inputs for collection.

## Overview

An Industry Config specifies:
- **Industry identity** (id, name, sector)
- **Companies** to collect data for
- **Macro requirements** (benchmarks, indices)
- **Data requirements** (metrics, history depth)

## Index View

### Features

- Filter by active/inactive status
- Search by name or industry_id
- Show validation status
- Create new config

### Columns

| Column | Description |
|--------|-------------|
| Industry ID | Machine identifier |
| Name | Human-readable name |
| Sector | Classification label |
| Companies | Number of companies |
| Status | Active/Inactive |
| Valid | Schema validation status |
| Updated | Last update timestamp |
| Actions | View, Edit, Toggle |

### Validation Status

| Status | Icon | Meaning |
|--------|------|---------|
| Valid | Green checkmark | Config passes JSON Schema validation |
| Invalid | Red X | Config has schema errors |

## Detail View

### Metadata

| Field | Description |
|-------|-------------|
| Industry ID | Machine identifier (immutable) |
| Name | Human-readable name |
| Sector | Classification label |
| Status | Active/Inactive |
| Created By | User who created |
| Created At | Creation timestamp |
| Updated By | User who last updated |
| Updated At | Last update timestamp |

### Config JSON

Read-only formatted view of the configuration:

```json
{
  "id": "integrated_oil_gas",
  "name": "Integrated Oil & Gas",
  "sector": "Energy",
  "companies": [
    {
      "ticker": "SHEL",
      "name": "Shell plc",
      "listing_exchange": "NYSE"
    }
  ],
  "macro_requirements": { ... },
  "data_requirements": { ... }
}
```

### Actions

| Action | Description |
|--------|-------------|
| Edit | Modify config JSON |
| Toggle Active | Enable/disable config |

## Create/Update

### JSON Editor

The create/update form provides a textarea for editing the config JSON:

```
┌────────────────────────────────────────┐
│  Industry Config JSON                  │
│  ┌──────────────────────────────────┐  │
│  │ {                                │  │
│  │   "id": "integrated_oil_gas",   │  │
│  │   "name": "Integrated Oil...",  │  │
│  │   ...                            │  │
│  │ }                                │  │
│  └──────────────────────────────────┘  │
│                                        │
│  [Format]  [Validate]  [Save]          │
└────────────────────────────────────────┘
```

### Editor Actions

| Button | Description |
|--------|-------------|
| Format | Pretty-print the JSON |
| Validate | Check against schema (without saving) |
| Save | Validate and save config |

### Validation Rules

- Must be valid JSON
- Must pass `industry-config.schema.json` validation
- `id` field is immutable after creation
- `id` must be unique across all configs

### Immutable Fields

After creation, the following cannot be changed:
- `id` (industry_id)

::: warning
Changing the `id` would break artifact paths and references. Create a new config instead.
:::

## Access Control

- **Authentication:** HTTP Basic Auth (env-driven)
- **Audit:** All changes logged with username

### Environment Variables

```bash
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your-secure-password
```

## Best Practices

### Naming Conventions

- Use lowercase with underscores for `id`: `integrated_oil_gas`
- Use title case for `name`: `Integrated Oil & Gas`
- Use standard sector names: `Energy`, `Technology`, `Healthcare`

### Company Configuration

```json
{
  "ticker": "SHEL",
  "name": "Shell plc",
  "listing_exchange": "NYSE",
  "listing_currency": "USD",
  "reporting_currency": "USD",
  "fy_end_month": 12
}
```

- Use primary listing ticker
- Specify correct currencies
- Set fiscal year end month (1-12)

### Data Requirements

Start with defaults, then customize:

```json
{
  "data_requirements": {
    "history_years": 5,
    "quarters_to_fetch": 4,
    "valuation_metrics": [
      { "key": "market_cap", "unit": "currency", "required": true },
      { "key": "fwd_pe", "unit": "ratio", "required": true }
    ]
  }
}
```

Mark only essential metrics as `required: true` to avoid collection failures.
