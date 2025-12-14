---
name: build-source-candidates
description: Generate prioritized list of source URLs for a ticker and datapoint type. Use when collect-company needs to determine where to fetch data. Maps ticker to provider URLs based on exchange and data type. Do NOT use for fetching (see collect-datapoint).
---

# BuildSourceCandidates

Generate source URLs for a given ticker and data requirement.

## Interface

```php
interface BuildSourceCandidatesInterface
{
    public function build(BuildCandidatesRequest $request): array;
}
```

## Input

```php
final readonly class BuildCandidatesRequest
{
    public function __construct(
        public string $ticker,
        public string $dataType,               // valuation|financials|quarters
        public CompanyConfig $config,
        public ?array $providerOverrides = null,
    ) {}
}
```

## Output

```php
/** @return SourceCandidate[] */
// Sorted by priority (lower = try first)

final readonly class SourceCandidate
{
    public function __construct(
        public string $providerId,
        public string $url,
        public int $priority,
        public string $adapterId,
    ) {}
}
```

## Algorithm

```
1. GET provider configs for dataType

2. FOR each provider IN providers (by priority):
   - BUILD URL using ticker + exchange mapping
   - IF alternative tickers exist, add those URLs too
   - SET adapterId for this provider

3. SORT by priority

4. RETURN SourceCandidate[]
```

## URL Templates

### Yahoo Finance
```
valuation:  https://finance.yahoo.com/quote/{TICKER}
financials: https://finance.yahoo.com/quote/{TICKER}/financials
profile:    https://finance.yahoo.com/quote/{TICKER}/profile
```

### Reuters
```
valuation:  https://www.reuters.com/companies/{TICKER}.{EXCHANGE}
financials: https://www.reuters.com/companies/{TICKER}.{EXCHANGE}/financials
```

Exchange mapping:
| Exchange | Reuters suffix |
|----------|----------------|
| NYSE | .N |
| NASDAQ | .O |
| LSE | .L |
| Euronext | .AS, .PA |

### Company IR
```
Requires search or config:
- Shell: https://www.shell.com/investors/results-and-reporting.html
- BP: https://www.bp.com/en/global/corporate/investors.html
```

## Definition of Done

- Returns at least one candidate (or empty if ticker unknown)
- Candidates sorted by priority
- Each candidate has correct adapterId
- Alternative tickers included if configured

## Example

```php
$request = new BuildCandidatesRequest(
    ticker: 'SHEL',
    dataType: 'valuation',
    config: new CompanyConfig(
        ticker: 'SHEL',
        name: 'Shell plc',
        listingExchange: 'NYSE',
        listingCurrency: 'USD',
        reportingCurrency: 'USD',
        fyEndMonth: 12,
        alternativeTickers: ['SHEL.L', 'SHEL.AS'],
    ),
);

$candidates = $builder->build($request);

// Returns:
// [
//   SourceCandidate('yahoo_finance', 'https://finance.yahoo.com/quote/SHEL', 1, 'yahoo_finance'),
//   SourceCandidate('reuters', 'https://www.reuters.com/companies/SHEL.N', 2, 'reuters'),
//   SourceCandidate('yahoo_finance', 'https://finance.yahoo.com/quote/SHEL.L', 3, 'yahoo_finance'),
// ]
```
