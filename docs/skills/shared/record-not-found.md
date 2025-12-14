---
name: record-not-found
description: Build a DataPoint for data that could not be found after exhausting sources. Use when collect-datapoint tries all candidates without success. Ensures attempted_sources documented. Do NOT use for found data (see record-provenance).
---

# RecordNotFound

Create a not_found DataPoint with complete attempt documentation.

## Interface

```php
interface RecordNotFoundInterface
{
    public function record(RecordNotFoundRequest $request): DataPoint;
}
```

## Input

```php
final readonly class RecordNotFoundRequest
{
    public function __construct(
        public string $datapointKey,
        public array $sourceAttempts,          // SourceAttempt[]
        public Severity $severity,
    ) {}
}

final readonly class SourceAttempt
{
    public function __construct(
        public string $providerId,
        public string $url,
        public SourceAttemptStatus $status,
        public DateTimeImmutable $attemptedAt,
        public ?string $errorReason = null,
    ) {}
}

enum SourceAttemptStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
```

## Output

```php
final readonly class DataPoint
{
    // For not_found:
    // - value: null
    // - method: CollectionMethod::NotFound
    // - attemptedSources: populated from sourceAttempts
}

final readonly class AttemptedSource
{
    public function __construct(
        public string $providerId,
        public string $url,
        public string $reason,
        public DateTimeImmutable $attemptedAt,
    ) {}
}
```

## Algorithm

```
1. TRANSFORM sourceAttempts to attemptedSources
   FOR each attempt:
       AttemptedSource(
           providerId: attempt.providerId,
           url: attempt.url,
           reason: attempt.errorReason ?? 'unknown',
           attemptedAt: attempt.attemptedAt,
       )

2. BUILD DataPoint
   - value: null
   - unit: infer from datapointKey or 'unknown'
   - asOf: latest attemptedAt date
   - sourceUrl: null
   - retrievedAt: latest attemptedAt
   - method: CollectionMethod::NotFound
   - sourceLocator: null
   - attemptedSources: transformed list

3. RETURN DataPoint
```

## Definition of Done

**All not_found datapoints must have:**
- `value` — null
- `method` — not_found
- `attemptedSources` — non-empty array
- Each attemptedSource has providerId, url, reason, attemptedAt

**Never:**
- Empty attemptedSources (gate will reject)
- Fabricated values
- Missing reasons

## Reason Codes

| Code | Meaning |
|------|---------|
| `http_4xx` | Client error (403, 404, etc.) |
| `http_5xx` | Server error |
| `timeout` | Request timed out |
| `parse_failed` | Could not parse response |
| `not_in_page` | Parsed but value not found |
| `stale` | Data too old |
| `rate_limited` | Hit rate limit |

