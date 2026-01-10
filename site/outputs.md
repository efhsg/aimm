# Outputs

AIMM writes pipeline outputs to the database and stores PDFs on disk.

## Phase 1 (Collect) Outputs

Data is stored in dossier tables:

- `annual_financial`
- `quarterly_financial`
- `valuation_snapshot`
- `macro_indicator`
- `price_history`

Each run is recorded in `collection_run` (status, counts, gate results).

## Phase 2 (Analyze) Outputs

Ranked reports are stored in `analysis_report`:

- `report_id` and `report_json` (RankedReportDTO)
- `rating` and `rule_path` summary fields

`metadata.data_as_of` reflects the latest available collection timestamp across
company dossier staleness fields and any macro indicators/benchmarks defined by
the collection policy.

CLI analysis saves reports to `analysis_report` by default. Use `--no-save` to
skip persistence. It can also output JSON to stdout or a file:

```bash
yii analyze/industry integrated_oil_gas --output=report.json
```

## Phase 3 (Render) Outputs

PDFs are stored via `StorageInterface`. Default storage uses local disk:

```
runtime/pdf-storage/reports/{YYYY}/{MM}/{reportId}_{jobId}.pdf
```
