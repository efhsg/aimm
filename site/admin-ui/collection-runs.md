# Collection Runs

Collection Runs show execution history and diagnostics for pipeline runs.

## Overview

A Collection Run represents one execution of Phase 1 (Collect) for a Peer Group.

## Index View

### Features

- Filter by status (Complete, Partial, Failed)
- Filter by Peer Group
- Search by industry/group name
- Pagination

### Columns

| Column | Description |
|--------|-------------|
| Peer Group | Associated peer group name |
| Industry | Industry identifier |
| Started | Run start timestamp |
| Status | Complete, Partial, Failed |
| Duration | Elapsed time |
| Companies | Collected / Total |
| Actions | View details |

### Status Badges

| Status | Color | Meaning |
|--------|-------|---------|
| Complete | Green | All data collected, gate passed |
| Partial | Yellow | Some data missing, gate passed with warnings |
| Failed | Red | Gate failed, errors present |
| Running | Blue | Currently in progress |

## Detail View

### Run Metadata

| Field | Description |
|-------|-------------|
| Peer Group | Link to peer group |
| Industry ID | Industry identifier |
| Started At | Run start timestamp |
| Completed At | Run end timestamp |
| Duration | Elapsed time |
| Status | Final status |
| Triggered By | User who initiated run |

### Output Artifacts

| Artifact | Path | Status |
|----------|------|--------|
| DataPack | `runtime/datapacks/{id}/{uuid}/datapack.json` | Generated/Missing |
| Validation | `runtime/datapacks/{id}/{uuid}/validation.json` | Generated/Missing |
| Report DTO | `runtime/datapacks/{id}/{uuid}/report-dto.json` | Generated/Missing |
| PDF Report | `runtime/datapacks/{id}/{uuid}/report.pdf` | Generated/Missing |

### Collection Summary

| Metric | Value |
|--------|-------|
| Companies Collected | 5 / 5 |
| Datapoints Collected | 342 |
| Datapoints Not Found | 18 |
| Macro Items | 8 |

### Errors Table

| Column | Description |
|--------|-------------|
| Code | Error code (e.g., `MISSING_REQUIRED_DATAPOINT`) |
| Company | Affected company ticker |
| Metric | Affected metric key |
| Message | Human-readable error message |

### Warnings Table

| Column | Description |
|--------|-------------|
| Code | Warning code |
| Company | Affected company (if applicable) |
| Message | Human-readable warning message |

## Common Errors

### MISSING_REQUIRED_DATAPOINT

A required metric could not be collected.

**Resolution:**
- Check source availability
- Verify ticker is correct
- Check for API rate limiting

### SCHEMA_VALIDATION_FAILED

Output doesn't match expected JSON Schema.

**Resolution:**
- Review collected data structure
- Check for null values in required fields
- Verify data types match schema

### MACRO_DATA_STALE

Macro data is older than freshness threshold.

**Resolution:**
- Re-run collection
- Check macro data sources
- Verify date parsing

### COMPANY_NOT_FOUND

A configured company couldn't be located.

**Resolution:**
- Verify ticker symbol
- Check exchange listing
- Update company configuration

## Re-running Failed Collections

1. Navigate to the failed run detail view
2. Review errors and warnings
3. Fix underlying issues (config, sources, etc.)
4. Go to Peer Group and click "Run Collection"

::: tip
Address errors before re-running. Repeated failures may indicate configuration issues.
:::
