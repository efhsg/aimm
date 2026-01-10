# CLI Usage

AIMM provides CLI commands for managing the data pipeline.

> **Note:** The pipeline is database-driven. Industry configurations must exist in the database before collection.

## Phase 1: Collect

### List Available Industries

Shows all industries currently configured in the database.

```bash
yii collect/list
```

**Output:**
```
+--------------------+--------------------+-----------+-----------+
| Industry           | Slug               | Sector    | Companies |
+--------------------+--------------------+-----------+-----------+
| Integrated Oil     | integrated_oil_gas | Energy    | 5         |
+--------------------+--------------------+-----------+-----------+
```

### Collect Industry Data

Triggers the collection process for a specific industry by its slug.

```bash
yii collect/industry integrated_oil_gas
```

**What it does:**
1.  Loads industry configuration from the database.
2.  Collects Macro data.
3.  Collects data for all companies in the industry.
4.  Validates the result (Collection Gate).
5.  Updates the persistent **Company Dossier** in the database.
6.  Records the run status.

**Output:**
```
+--------------------+--------------------------------------+----------+----------+
| Industry           | Datapack ID                          | Status   | Duration |
+--------------------+--------------------------------------+----------+----------+
| integrated_oil_gas | 550e8400-e29b-41d4-a716-446655440000 | Complete | 45.20s   |
+--------------------+--------------------------------------+----------+----------+
```

## Phase 2: Analyze

### Analyze Industry

Analyzes the latest successfully collected data for an industry.

```bash
yii analyze/industry integrated_oil_gas
```

**What it does:**
1.  Finds the latest successful Collection Run for the industry.
2.  Builds an IndustryDataPack from dossier tables.
3.  Calculates peer averages, valuation gaps, and ratings for analyzable companies.
4.  Generates a Ranked Report (RankedReportDTO).
5.  Saves the Ranked Report to `analysis_report` by default.
6.  Outputs the report JSON to stdout (or file).

### Options

| Option | Description |
|--------|-------------|
| `--output=<path>` | Write the Report JSON to a specific file instead of stdout. |
| `--no-save` | Skip saving the report to `analysis_report`. |

**Example:**
```bash
yii analyze/industry integrated_oil_gas --output=report.json
```

**Note:** Companies without at least 2 years of annual data or missing market cap
are skipped in the ranking.

## Phase 3: Render (PDF)

While testing is performed via CLI, actual report generation is triggered through the Web API.

### Generate Actual Report

To generate a PDF for an analysis report, use the following API flow:

#### 1. Create Generation Job
Initiate the generation process using a Report ID (found in Phase 2 output).

```bash
curl -X POST http://localhost:8510/api/reports/generate \
  -H "Content-Type: application/json" \
  -d '{"reportId": "rpt_20260110_..."}'
```
**Response:** `{"jobId": 123}`

#### 2. Check Job Status
Poll the status endpoint using the `jobId` from the previous step.

```bash
curl http://localhost:8510/api/jobs/123
```
**Response:** `{"status": "complete", "outputUri": "..."}`

#### 3. Download Report
Once the status is `complete`, download the resulting PDF.

```bash
curl -O http://localhost:8510/api/reports/rpt_20260110_.../download
```

### Test PDF Generation

Verify the connection to the Gotenberg rendering service:

```bash
yii pdf/test
```

**Output:**
```
Generating test PDF with traceId: test-20231027103000
PDF generated: /app/runtime/test-test-20231027103000.pdf
Size: 15420 bytes
```

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Unspecified Error |
| 65 | Data Error (e.g., Industry not found, data missing) |
