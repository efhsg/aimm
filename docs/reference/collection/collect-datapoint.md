---
name: collect-datapoint
description: Collect a single datapoint from prioritized sources with full provenance. Use when a handler needs ONE specific metric (e.g., fwd_pe, market_cap). Do NOT use for batch collection (see collect-company) or parsing already-fetched content (see adapt-source-response).
---

# CollectDatapoint

Fetch a single datapoint from sources until found or all exhausted.

## Interface

```php
interface CollectDatapointInterface
{
    public function collect(CollectDatapointRequest $request): CollectDatapointResult;
}
```

## Input

```php
final readonly class CollectDatapointRequest
{
    public function __construct(
        public string $datapointKey,           // e.g., 'valuation.fwd_pe'
        public array $sourceCandidates,        // SourceCandidate[] in priority order
        public string $adapterId,              // e.g., 'yahoo_finance'
        public Severity $severity,             // required|recommended|optional
        public ?string $ticker = null,
        public ?DateTimeImmutable $asOfMin = null,
    ) {}
}

final readonly class SourceCandidate
{
    public function __construct(
        public string $providerId,
        public string $url,
        public int $priority,
    ) {}
}
```

## Output

```php
final readonly class CollectDatapointResult
{
    public function __construct(
        public string $datapointKey,
        public ?DataPoint $datapoint,
        public array $sourceAttempts,          // SourceAttempt[]
        public bool $found,
    ) {}
}
```

## Algorithm

```
FOR each candidate IN sourceCandidates (by priority):
    1. Check rate limit → wait or skip
    2. Fetch URL → on fail, record attempt, continue
    3. Adapt response → on fail, record attempt, continue
    4. Validate freshness → on stale, record attempt, continue
    5. Build DataPoint with provenance
    6. RETURN found=true

IF no candidate succeeded:
    Build DataPoint with method=not_found
    RETURN found=false
```

## Definition of Done

**Found:**
- `datapoint.value` is not null
- `datapoint.sourceUrl` set
- `datapoint.retrievedAt` set
- `datapoint.sourceLocator` contains selector + snippet

**Not found:**
- `datapoint.method` is `not_found`
- `datapoint.attemptedSources` lists all tried sources with reasons

## Error Handling

| Scenario | Action | Reason code |
|----------|--------|-------------|
| HTTP 4xx | Next source | `http_4xx` |
| HTTP 5xx | Retry once, then next | `http_5xx` |
| Parse failure | Next source | `parse_failed` |
| Value missing | Next source | `not_in_page` |
| Data stale | Next source | `stale` |
