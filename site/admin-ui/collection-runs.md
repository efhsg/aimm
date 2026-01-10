# Collection Runs

Collection Runs show execution history and diagnostics for Phase 1.

## Index View

### Features

- Filter by status (Running, Complete, Failed)
- Search by industry slug/name or datapack ID
- Pagination

Note: A `partial` status can appear in the list, but it is not a filter option.

### Columns

| Column | Description |
|--------|-------------|
| Run ID | Internal run identifier |
| Industry | Industry ID (numeric) |
| Datapack ID | UUID generated per run |
| Status | running/complete/failed |
| Gate | Passed/Failed (only for complete) |
| Started | Run start timestamp |
| Completed | Run end timestamp |
| Duration | Elapsed time |
| Companies | Success/Total counts |
| Issues | Error and warning counts |
| Actions | View details |

## Detail View

### Run Metadata

| Field | Description |
|-------|-------------|
| Industry | Numeric industry ID |
| Datapack ID | UUID for the run |
| Started | Run start timestamp |
| Completed | Run end timestamp |
| Duration | Elapsed time |
| Companies | Success/failed counts |
| Gate | Passed/Failed (if complete) |

If `file_path` is present, the UI shows the stored file path and size.

### Errors and Warnings

| Column | Description |
|--------|-------------|
| Ticker | Company ticker (if applicable) |
| Code | Error or warning code |
| Message | Human-readable message |
| Path | Data path when available |

### Collected Data Snapshots

The detail page shows dossier snapshots:

- Annual financials (filter by year)
- Valuation snapshots (filter by date)
- Macro indicators (filter by date)
