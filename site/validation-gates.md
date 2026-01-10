# Validation Gates

Gates are checkpoints between phases. In the current pipeline, gates are implemented
as status checks and minimum data checks.

## Collection Gate (after Phase 1)

Collection gate validation is based on collection results:

| Check | Description | Failure Severity |
|-------|-------------|------------------|
| Company status | `failed` companies fail the gate | Error |
| Company status | `partial` companies are warnings | Warning |
| Macro status | `failed` macro collection fails the gate | Error |
| Macro status | `partial` macro collection warns | Warning |
| Missing companies | Configured tickers not collected | Warning |

## Analysis Gate (after Phase 2)

Analysis gate validation checks minimum data requirements:

| Check | Description | Failure Severity |
|-------|-------------|------------------|
| Minimum companies | At least 2 companies required | Error |
| Analyzable companies | At least 2 companies with >= 2 years + market cap | Error |
| Data freshness | Warn if data older than 30 days | Warning |

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
| 1 | `ExitCode::UNSPECIFIED_ERROR` | Internal error |
| 65 | `ExitCode::DATAERR` | Gate failed with errors |
