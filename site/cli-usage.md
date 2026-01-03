# CLI Usage

AIMM provides CLI commands for each pipeline phase.

## Phase 1: Collect

### List Available Industries

```bash
yii collect/list
```

Shows all configured industries in `config/industries/`.

### Collect Industry Data

```bash
yii collect/industry integrated_oil_gas
```

**Output:**
- `runtime/datapacks/integrated_oil_gas/{uuid}/datapack.json`
- `runtime/datapacks/integrated_oil_gas/{uuid}/validation.json`

### Options

| Option | Description |
|--------|-------------|
| `--force` | Re-collect even if recent datapack exists |
| `--company=SHEL` | Collect only specified company |

## Phase 2: Analyze

### Generate Report DTO

```bash
yii analyze/report \
    --datapack=runtime/datapacks/integrated_oil_gas/{uuid}/datapack.json \
    --focal=SHEL \
    --peers=BP,XOM,CVX,TTE
```

**Output:**
- `runtime/datapacks/integrated_oil_gas/{uuid}/report-dto.json`

### Options

| Option | Description |
|--------|-------------|
| `--datapack` | Path to IndustryDataPack JSON (required) |
| `--focal` | Ticker of focal company (required) |
| `--peers` | Comma-separated list of peer tickers (required) |

## Phase 3: Render

### Generate PDF

```bash
yii render/pdf \
    --dto=runtime/datapacks/integrated_oil_gas/{uuid}/report-dto.json
```

**Output:**
- `runtime/datapacks/integrated_oil_gas/{uuid}/report.pdf`

### Options

| Option | Description |
|--------|-------------|
| `--dto` | Path to ReportDTO JSON (required) |
| `--output` | Custom output path (optional) |

## Full Pipeline

### Run All Phases

```bash
yii pipeline/run integrated_oil_gas --focal=SHEL --peers=BP,XOM,CVX,TTE
```

Runs collect → analyze → render in sequence.

### Options

| Option | Description |
|--------|-------------|
| `--focal` | Ticker of focal company (required) |
| `--peers` | Comma-separated list of peer tickers (required) |
| `--skip-collect` | Use existing datapack |
| `--skip-render` | Stop after analysis |

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 65 | Data/validation error (gate failed) |
| 70 | Internal error (exception) |

## Examples

### Collect and Analyze Oil & Gas

```bash
# Collect industry data
yii collect/industry integrated_oil_gas

# Analyze Shell vs peers
yii analyze/report \
    --datapack=runtime/datapacks/integrated_oil_gas/latest/datapack.json \
    --focal=SHEL \
    --peers=BP,XOM,CVX,TTE

# Render PDF
yii render/pdf \
    --dto=runtime/datapacks/integrated_oil_gas/latest/report-dto.json
```

### Full Pipeline in One Command

```bash
yii pipeline/run integrated_oil_gas --focal=SHEL --peers=BP,XOM,CVX,TTE
```
