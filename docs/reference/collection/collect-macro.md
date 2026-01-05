---
name: collect-macro
description: Collect macro/market datapoints (commodity prices, margin proxies) for an industry. Use when CollectIndustryHandler needs market context data. Do NOT use for company data (see collect-company) or single datapoints (see collect-datapoint).
---

# CollectMacro

Gather industry-level macro data (commodities, spreads, benchmarks).

## Interface

```php
interface CollectMacroInterface
{
    public function collect(CollectMacroRequest $request): CollectMacroResult;
}
```

## Input

```php
final readonly class CollectMacroRequest
{
    public function __construct(
        public string $industryId,
        public MacroRequirements $requirements,
    ) {}
}

final readonly class MacroRequirements
{
    public function __construct(
        public ?CommodityBenchmark $commodityBenchmark,
        public ?MarginProxy $marginProxy,
        public array $additionalIndicators = [],
    ) {}
}

final readonly class CommodityBenchmark
{
    public function __construct(
        public string $name,                   // "Brent Crude"
        public array $searchTerms,             // ["brent crude price"]
    ) {}
}
```

## Output

```php
final readonly class CollectMacroResult
{
    public function __construct(
        public MacroData $data,
        public array $sourceAttempts,
        public CollectionStatus $status,
    ) {}
}

final readonly class MacroData
{
    public function __construct(
        public ?CommodityPrice $commodityPrice,
        public ?MarginData $marginProxy,
        public array $additionalIndicators,
    ) {}
}

final readonly class CommodityPrice
{
    public function __construct(
        public DataPoint $spotPrice,
        public ?DataPoint $ytdChange,
        public ?DataPoint $fiftyTwoWeekHigh,
        public ?DataPoint $fiftyTwoWeekLow,
    ) {}
}
```

## Algorithm

```
1. COLLECT commodity benchmark
   IF requirements.commodityBenchmark:
       sources = [EIA, commodity exchanges, financial news]
       spotPrice = CollectDatapoint('commodity.spot', sources, required)
       ytdChange = CollectDatapoint('commodity.ytd', sources, recommended)
       range = CollectDatapoint('commodity.52w_range', sources, optional)

2. COLLECT margin proxy
   IF requirements.marginProxy:
       IF proxy.type == 'crack_spread':
           - Fetch component prices (crude, gasoline, heating oil)
           - Calculate derived value
           - Record derivedFrom paths
       ELSE:
           sources = [industry sources]
           margin = CollectDatapoint('margin.proxy', sources, required)

3. COLLECT additional indicators
   FOR each indicator IN requirements.additionalIndicators:
       result = CollectDatapoint(indicator.key, indicator.sources, indicator.severity)
       ADD to additionalIndicators

4. DETERMINE status
   - complete: commodity + margin found
   - partial: commodity found, margin missing
   - failed: commodity missing

5. RETURN CollectMacroResult
```

## Definition of Done

**Complete:**
- Commodity spot price found with provenance
- Margin proxy found or calculated with derivation
- All datapoints within freshness threshold

**Partial:**
- Commodity found but margin missing
- Missing items documented

**Failed:**
- No commodity price available
- Should not proceed (market context required)

## Freshness Rules

| Macro type | Max age | Rationale |
|------------|---------|-----------|
| Commodity spot | 3 days | Prices move daily |
| YTD change | 7 days | Relative metric |
| Margin proxy | 10 days | Calculated weekly |

## Derived Datapoints

For calculated values (e.g., crack spread):

```php
[
    'value' => 15.2,
    'unit' => 'currency',
    'currency' => 'USD',
    'method' => 'derived',
    'derivedFrom' => [
        'macro.gasoline_price',
        'macro.heating_oil_price', 
        'macro.crude_price',
    ],
    'formula' => '(2 * gasoline + 1 * heating_oil - 3 * crude) / 3',
]
```
