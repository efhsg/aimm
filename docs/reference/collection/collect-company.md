---
name: collect-company
description: Collect all datapoints for a single company (valuation, financials, quarters). Use when CollectIndustryHandler needs to gather complete company data. Orchestrates multiple collect-datapoint calls. Do NOT use for single metrics (see collect-datapoint) or macro data (see collect-macro).
---

# CollectCompany

Gather all configured datapoints for one company.

## Interface

```php
interface CollectCompanyInterface
{
    public function collect(CollectCompanyRequest $request): CollectCompanyResult;
}
```

## Input

```php
final readonly class CollectCompanyRequest
{
    public function __construct(
        public string $ticker,
        public CompanyConfig $config,          // from IndustryConfig
        public DataRequirements $requirements, // required/optional metrics
    ) {}
}

final readonly class CompanyConfig
{
    public function __construct(
        public string $ticker,
        public string $name,
        public string $listingExchange,
        public string $listingCurrency,
        public string $reportingCurrency,
        public int $fyEndMonth,
        public ?array $alternativeTickers = null,
    ) {}
}
```

## Output

```php
final readonly class CollectCompanyResult
{
    public function __construct(
        public string $ticker,
        public CompanyData $data,
        public array $sourceAttempts,          // all attempts across all datapoints
        public CollectionStatus $status,       // complete|partial|failed
    ) {}
}

final readonly class CompanyData
{
    public function __construct(
        public string $ticker,
        public ValuationData $valuation,
        public FinancialsData $financials,
        public QuartersData $quarters,
        public ?OperationalData $operational = null,
    ) {}
}
```

## Algorithm

```
1. BUILD source candidates for ticker
   - Primary: Yahoo Finance, Reuters
   - Fallback: Company IR, alternative tickers

2. COLLECT valuation metrics
   FOR each metric IN requirements.valuationMetrics:
       result = CollectDatapoint(metric, sources, severity)
       ADD to valuation

3. COLLECT financial history
   FOR each year IN requirements.historyYears:
       FOR each metric IN [revenue, ebitda, netIncome, netDebt]:
           result = CollectDatapoint(metric, sources, severity)
           ADD to financials

4. COLLECT quarterly data
   FOR each quarter IN requirements.quartersToFetch:
       result = CollectDatapoint(quarterUrl, sources, required)
       ADD to quarters

5. DETERMINE status
   - complete: all required found
   - partial: some required missing but documented
   - failed: critical data missing

6. RETURN CollectCompanyResult
```

## Definition of Done

**Complete:**
- All required valuation metrics found OR documented not_found
- Financial history for configured years
- Quarterly URLs for configured quarters
- All datapoints have provenance

**Partial:**
- Some required metrics missing
- Missing items documented with attempted_sources
- Enough data for analysis to proceed

**Failed:**
- Critical metrics missing (market_cap, fwd_pe)
- No financial history available
- Should not proceed to analysis

## Source Priority

| Data type | Source 1 | Source 2 | Source 3 |
|-----------|----------|----------|----------|
| Valuation | Yahoo Finance | Reuters | Company IR |
| Financials | Company IR | Yahoo Finance | SEC |
| Quarters | Company IR | Earnings calendar | News |
