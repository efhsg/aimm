# Outputs

AIMM generates artifacts in the `runtime/datapacks/` directory.

## Artifact Structure

```
runtime/datapacks/
└── {industry_id}/
    └── {uuid}/
        ├── datapack.json      # Phase 1 output
        ├── validation.json    # Gate validation result
        ├── report-dto.json    # Phase 2 output
        └── report.pdf         # Phase 3 output
```

## Artifact Files

### datapack.json

**Phase:** 1 (Collect)

Contains all collected data for the industry:
- Macro data (commodity prices, indices)
- Company data (valuation, financials, operational metrics)

```json
{
  "industry_id": "integrated_oil_gas",
  "collected_at": "2025-12-13T10:30:00Z",
  "macro": {
    "commodity_benchmark": { ... },
    "sector_index": { ... }
  },
  "companies": {
    "SHEL": {
      "valuation": { ... },
      "annual_financials": { ... },
      "quarters": { ... }
    }
  }
}
```

### validation.json

**Phase:** 1 & 2 (Gate outputs)

Contains gate validation results:

```json
{
  "gate": "collection",
  "passed": true,
  "errors": [],
  "warnings": [
    {
      "code": "INSUFFICIENT_HISTORY",
      "company": "SHEL",
      "message": "Only 3 years available"
    }
  ],
  "validated_at": "2025-12-13T10:31:00Z"
}
```

### report-dto.json

**Phase:** 2 (Analyze)

Contains analyzed data ready for rendering:

```json
{
  "focal": {
    "ticker": "SHEL",
    "name": "Shell plc",
    "valuation": { ... },
    "valuation_gap": 21.5,
    "rating": "BUY",
    "rule_path": "BUY_ALL_CONDITIONS"
  },
  "peers": [ ... ],
  "peer_averages": { ... },
  "macro": { ... },
  "generated_at": "2025-12-13T10:32:00Z"
}
```

### report.pdf

**Phase:** 3 (Render)

The final PDF report containing:
- Executive summary with rating
- Valuation comparison tables
- Historical charts
- Peer analysis
- Risk factors

## Naming Conventions

| Context | Pattern | Example |
|---------|---------|---------|
| Artifact folders | `{industry_id}/{uuid}/` | `integrated_oil_gas/a1b2c3d4/` |
| Log files | `aimm-*.log` | `aimm-collection-2025-12-13.log` |
| Temp files | `aimm-tmp-*` | `aimm-tmp-render-abc123` |

## File Locations

| Type | Path |
|------|------|
| Datapacks | `runtime/datapacks/{industry_id}/{uuid}/` |
| Logs | `runtime/logs/` |
| Cache | `runtime/cache/` |

## Retention

Artifacts are retained indefinitely by default. Implement cleanup policies as needed:

```bash
# Example: Remove datapacks older than 30 days
find runtime/datapacks -type d -mtime +30 -exec rm -rf {} +
```

## Accessing Artifacts

### Latest Datapack

```bash
# Find most recent datapack for an industry
ls -t runtime/datapacks/integrated_oil_gas/ | head -1
```

### Via CLI

```bash
# Analyze using latest datapack
yii analyze/report \
    --datapack=runtime/datapacks/integrated_oil_gas/latest/datapack.json \
    --focal=SHEL \
    --peers=BP,XOM,CVX,TTE
```

::: info Note
The `latest/` symlink is created after each successful collection, pointing to the most recent UUID folder.
:::
