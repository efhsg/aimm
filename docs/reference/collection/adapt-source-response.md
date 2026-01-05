---
name: adapt-source-response
description: Parse a fetched response (HTML/JSON) into structured datapoints using a source-specific adapter. Use when you have raw content from WebFetchClient and need to extract metrics with source locators. Do NOT use for fetching (see collect-datapoint) or validation (see validate-collection-gate).
---

# AdaptSourceResponse

Extract datapoints from fetched content using the appropriate adapter.

## Interface

```php
interface AdaptSourceResponseInterface
{
    public function adapt(AdaptRequest $request): AdaptResult;
}
```

## Input

```php
final readonly class AdaptRequest
{
    public function __construct(
        public FetchResult $fetchResult,
        public string $adapterId,
        public array $datapointKeys,           // keys to extract
        public ?string $ticker = null,
    ) {}
}

final readonly class FetchResult
{
    public function __construct(
        public string $content,
        public string $contentType,            // text/html, application/json
        public int $statusCode,
        public string $url,
        public DateTimeImmutable $retrievedAt,
    ) {}
}
```

## Output

```php
final readonly class AdaptResult
{
    public function __construct(
        public string $adapterId,
        public array $extractions,             // datapointKey => Extraction
        public array $notFound,                // datapointKey[]
        public ?string $parseError = null,
    ) {}
}

final readonly class Extraction
{
    public function __construct(
        public string $datapointKey,
        public mixed $rawValue,
        public string $unit,
        public ?string $currency = null,
        public ?DateTimeImmutable $asOf = null,
        public SourceLocator $locator,
    ) {}
}

final readonly class SourceLocator
{
    public function __construct(
        public string $type,                   // html|json|xpath
        public string $selector,
        public string $snippet,                // max 100 chars
    ) {}
}
```

## Algorithm

```
1. SELECT adapter by adapterId
   - IF not found → RETURN parseError: 'unknown_adapter'

2. PARSE content by contentType
   - IF fails → RETURN parseError: 'invalid_content'

3. FOR each key IN datapointKeys:
   - GET selector from adapter
   - EXTRACT value
   - IF found → add to extractions with SourceLocator
   - ELSE → add to notFound

4. RETURN AdaptResult
```

## Definition of Done

**Extracted:**
- `extraction.rawValue` populated
- `extraction.locator.selector` is the exact selector used
- `extraction.locator.snippet` contains recognizable context

**Not found:**
- Key in `notFound` array
- No exception thrown

**Parse failure:**
- `parseError` set
- All keys in `notFound`

## Value Normalization

Adapters handle source-specific formatting:

| Pattern | Example | Normalized |
|---------|---------|------------|
| Abbreviation | "1.5T" | 1500000000000 |
| Percentage | "3.50%" | 3.5 |
| N/A variants | "N/A", "-", "--" | null |
| Thousands separator | "1,234.56" | 1234.56 |
