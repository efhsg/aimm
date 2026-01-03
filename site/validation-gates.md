# Validation Gates

Gates are checkpoints between phases that prevent bad data from flowing downstream. If a gate fails, the pipeline stops.

## Collection Gate (after Phase 1)

The Collection Gate validates the `IndustryDataPack` before it can be used for analysis.

### Checks Performed

| Check | Description | Failure Severity |
|-------|-------------|------------------|
| Schema compliance | DataPack matches `industry-datapack.schema.json` | Error |
| Required datapoints | All metrics marked `required: true` have values | Error |
| Company coverage | All companies in config are present in output | Error |
| Macro freshness | Macro data retrieved within 10 days | Error |
| History depth | Minimum years of financial history present | Warning |

### Example Failure Output

```json
{
  "passed": false,
  "errors": [
    {
      "code": "MISSING_REQUIRED_DATAPOINT",
      "company": "SHEL",
      "metric": "fwd_pe",
      "message": "Required valuation metric 'fwd_pe' is missing"
    }
  ],
  "warnings": [
    {
      "code": "INSUFFICIENT_HISTORY",
      "company": "SHEL",
      "expected": 5,
      "actual": 3,
      "message": "Only 3 years of annual data available (expected 5)"
    }
  ]
}
```

## Analysis Gate (after Phase 2)

The Analysis Gate validates the `ReportDTO` before rendering.

### Checks Performed

| Check | Description | Failure Severity |
|-------|-------------|------------------|
| Schema compliance | ReportDTO matches `report-dto.schema.json` | Error |
| Peer average recomputation | Recalculated averages match reported values | Error |
| Valuation gap recomputation | Recalculated gap matches reported value | Error |
| Rating rule path | Re-running rules produces same rating | Error |
| Temporal sanity | No past dates marked as "upcoming" | Warning |

### Why Recomputation?

The Analysis Gate recomputes key values to ensure:

1. **Determinism**: Same inputs always produce same outputs
2. **Auditability**: Values can be verified independently
3. **Integrity**: No calculation errors slipped through

### Example Failure Output

```json
{
  "passed": false,
  "errors": [
    {
      "code": "PEER_AVERAGE_MISMATCH",
      "metric": "fwd_pe",
      "reported": 15.2,
      "recomputed": 14.8,
      "message": "Peer average mismatch for 'fwd_pe'"
    }
  ],
  "warnings": []
}
```

## Gate Result Structure

```php
class GateResult
{
    public function __construct(
        public bool $passed,
        public array $errors,    // Fatal issues - stop pipeline
        public array $warnings,  // Non-fatal - log and continue
    ) {}
}
```

## Exit Codes

| Code | Constant | When |
|------|----------|------|
| 0 | `ExitCode::OK` | Gate passed |
| 65 | `ExitCode::DATAERR` | Gate failed with errors |
| 70 | `ExitCode::SOFTWARE` | Internal error during validation |

## Gate Bypass

::: danger Not Recommended
Gates should never be bypassed in production. They exist to prevent downstream errors.
:::

For debugging purposes only, gates can be run in "warn-only" mode during development.
