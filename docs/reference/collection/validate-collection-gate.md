---
name: validate-collection-gate
description: Validate an IndustryDataPack after collection completes. Gate between Phase 1 and Phase 2. Checks schema, required datapoints, provenance, and freshness. Use before passing datapack to analysis. Do NOT use for analysis validation (see validate-analysis-gate).
---

# ValidateCollectionGate

Ensure collected datapack meets quality requirements before analysis.

## Interface

```php
interface ValidateCollectionGateInterface
{
    public function validate(IndustryDataPack $dataPack, IndustryConfig $config): GateResult;
}
```

## Input

```php
final readonly class IndustryDataPack
{
    public function __construct(
        public string $industryId,
        public string $datapackId,
        public DateTimeImmutable $collectedAt,
        public MacroData $macro,
        public array $companies,               // ticker => CompanyData
        public CollectionLog $collectionLog,
    ) {}
}
```

## Output

```php
final readonly class GateResult
{
    public function __construct(
        public bool $passed,
        public array $errors,                  // GateError[] - blocks pipeline
        public array $warnings,                // GateWarning[] - logged only
    ) {}
}

final readonly class GateError
{
    public function __construct(
        public string $code,
        public string $message,
        public ?string $path = null,
    ) {}
}
```

## Validation Rules

### Schema (errors)
```
VALIDATE against industry-datapack.schema.json
IF invalid → error(SCHEMA_INVALID)
```

### Company Completeness (errors)
```
FOR each ticker IN config.companyTickers:
    IF missing → error(MISSING_COMPANY)
```

### Required Datapoints (errors)
```
FOR each company, each required metric:
    IF null → error(MISSING_REQUIRED)
    IF not_found without attemptedSources → error(UNDOCUMENTED_MISSING)
```

### Provenance (errors)
```
FOR each found datapoint:
    IF no sourceUrl → error(MISSING_PROVENANCE)
    IF no retrievedAt → error(MISSING_PROVENANCE)
FOR each not_found datapoint:
    IF no attemptedSources → error(MISSING_ATTEMPTS)
FOR each derived datapoint:
    IF no derivedFrom → error(MISSING_DERIVATION)
```

### Macro Freshness (errors)
```
FOR each macro datapoint:
    IF age > threshold → error(MACRO_STALE)
```

### Warnings
```
- EXTRA_COMPANY: unexpected ticker in datapack
- MISSING_LOCATOR: found datapoint lacks selector
- MACRO_AGING: approaching staleness
- TEMPORAL_SPREAD: collection span > 24h
- LOW_COVERAGE: optional metric < 50% coverage
```

## Definition of Done

**Pass:**
- Zero errors
- Schema valid
- All companies present
- All required metrics present or documented
- Provenance complete
- Macro fresh

**Fail:**
- Any error present
- Returns actionable error list

## Error Codes

| Code | Severity | Trigger |
|------|----------|---------|
| `SCHEMA_INVALID` | error | JSON Schema fails |
| `MISSING_COMPANY` | error | Configured company absent |
| `MISSING_REQUIRED` | error | Required metric null |
| `UNDOCUMENTED_MISSING` | error | not_found lacks attempts |
| `MISSING_PROVENANCE` | error | Found lacks source |
| `MISSING_ATTEMPTS` | error | not_found lacks attempts |
| `MISSING_DERIVATION` | error | derived lacks sources |
| `MACRO_STALE` | error | Exceeds threshold |
| `EXTRA_COMPANY` | warning | Unexpected ticker |
| `MISSING_LOCATOR` | warning | No selector recorded |
| `MACRO_AGING` | warning | Approaching stale |
| `TEMPORAL_SPREAD` | warning | > 24h span |
| `LOW_COVERAGE` | warning | < 50% optional |
