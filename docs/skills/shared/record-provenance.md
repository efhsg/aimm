---
name: record-provenance
description: Build a complete DataPoint with provenance from raw extraction data. Use when adapt-source-response returns an Extraction and you need a fully-formed DataPoint. Ensures all provenance fields populated. Do NOT use for not_found datapoints (see record-not-found).
---

# RecordProvenance

Transform raw extraction into DataPoint with complete provenance.

## Interface

```php
interface RecordProvenanceInterface
{
    public function record(RecordProvenanceRequest $request): DataPoint;
}
```

## Input

```php
final readonly class RecordProvenanceRequest
{
    public function __construct(
        public Extraction $extraction,
        public FetchResult $fetchResult,
        public string $datapointKey,
    ) {}
}
```

## Output

```php
final readonly class DataPoint
{
    public function __construct(
        public mixed $value,
        public string $unit,
        public ?string $currency,
        public DateTimeImmutable $asOf,
        public string $sourceUrl,
        public DateTimeImmutable $retrievedAt,
        public CollectionMethod $method,
        public SourceLocator $sourceLocator,
        public ?array $attemptedSources = null,  // only for not_found
        public ?array $derivedFrom = null,       // only for derived
        public ?string $formula = null,          // only for derived
    ) {}
}

enum CollectionMethod: string
{
    case WebFetch = 'web_fetch';
    case WebSearch = 'web_search';
    case Api = 'api';
    case Derived = 'derived';
    case NotFound = 'not_found';
}
```

## Algorithm

```
1. EXTRACT value from extraction.rawValue
   - Already normalized by adapter

2. DETERMINE asOf
   - IF extraction.asOf set → use it
   - ELSE → use fetchResult.retrievedAt date

3. BUILD DataPoint
   - value: extraction.rawValue
   - unit: extraction.unit
   - currency: extraction.currency
   - asOf: determined above
   - sourceUrl: fetchResult.url
   - retrievedAt: fetchResult.retrievedAt
   - method: CollectionMethod::WebFetch
   - sourceLocator: extraction.locator

4. RETURN DataPoint
```

## Definition of Done

**All found datapoints must have:**
- `value` — not null
- `unit` — ratio|percent|currency|number
- `asOf` — date data represents
- `sourceUrl` — URL fetched
- `retrievedAt` — fetch timestamp
- `method` — web_fetch|api|web_search
- `sourceLocator.selector` — how to find it
- `sourceLocator.snippet` — context (max 100 chars)

## Validation

```php
public function validate(DataPoint $dp): array
{
    $errors = [];
    
    if ($dp->value === null && $dp->method !== CollectionMethod::NotFound) {
        $errors[] = 'Found datapoint has null value';
    }
    if (empty($dp->sourceUrl) && $dp->method !== CollectionMethod::Derived) {
        $errors[] = 'Missing sourceUrl';
    }
    if ($dp->retrievedAt === null) {
        $errors[] = 'Missing retrievedAt';
    }
    if ($dp->sourceLocator === null && $dp->method === CollectionMethod::WebFetch) {
        $errors[] = 'WebFetch missing sourceLocator';
    }
    
    return $errors;
}
```

