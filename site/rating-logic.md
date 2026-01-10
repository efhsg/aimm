# Rating Logic

AIMM uses a deterministic, rule-based system to determine BUY/HOLD/SELL ratings.

Output values are emitted as lowercase strings: `buy`, `hold`, `sell`.

## Rating Decision Tree

```
IF fundamentals == "Deteriorating":
    rating = SELL, rule_path = "SELL_FUNDAMENTALS"

ELSE IF risk == "Unacceptable":
    rating = SELL, rule_path = "SELL_RISK"

ELSE IF valuation_gap == null:
    rating = HOLD, rule_path = "HOLD_INSUFFICIENT_DATA"

ELSE IF valuation_gap > 15% AND fundamentals == "Improving" AND risk == "Acceptable":
    rating = BUY, rule_path = "BUY_ALL_CONDITIONS"

ELSE:
    rating = HOLD, rule_path = "HOLD_DEFAULT"
```

## Rule Paths

Each rating includes a `rule_path` that explains why the rating was assigned:

| Rule Path | Rating | Condition |
|-----------|--------|-----------|
| `SELL_FUNDAMENTALS` | sell | Fundamentals are deteriorating |
| `SELL_RISK` | sell | Risk level is unacceptable |
| `HOLD_INSUFFICIENT_DATA` | hold | Cannot calculate valuation gap |
| `BUY_ALL_CONDITIONS` | buy | Gap > 15%, improving fundamentals, acceptable risk |
| `HOLD_DEFAULT` | hold | Does not meet BUY criteria, not a SELL |

## Input Factors

### Fundamentals

| Value | Description |
|-------|-------------|
| Improving | Revenue/earnings growth, margin expansion |
| Mixed | Some metrics improving, some declining |
| Deteriorating | Revenue/earnings decline, margin compression |

### Risk

| Value | Description |
|-------|-------------|
| Acceptable | Normal business risks |
| Elevated | Specific concerns but manageable |
| Unacceptable | Material risks that could impair value |

### Valuation Gap

A composite measure of how "cheap" a company is versus the industry average.

## Valuation Gap Calculation

```
For each metric (fwd_pe, ev_ebitda, fcf_yield, div_yield):
    IF company_value AND group_avg both non-null:
        For P/E, EV/EBITDA (lower is better):
            gap = ((group_avg - company_value) / group_avg) × 100
        For yields (higher is better):
            gap = ((company_value - group_avg) / group_avg) × 100

IF gaps.count >= 2:
    composite_gap = average(gaps)
ELSE:
    composite_gap = null
```

### Example Calculation

| Metric | Company | Group Avg | Gap |
|--------|-------|----------|-----|
| fwd_pe | 10.0 | 12.5 | +20% (cheaper) |
| ev_ebitda | 5.0 | 6.0 | +16.7% (cheaper) |
| fcf_yield | 8.0% | 6.0% | +33.3% (better) |
| div_yield | 4.0% | 3.5% | +14.3% (better) |

**Composite Gap**: (20 + 16.7 + 33.3 + 14.3) / 4 = **21.1%**

### Minimum Metrics Required

At least **2 metrics** must be calculable to produce a composite gap. If fewer are available:
- `valuation_gap = null`
- Rating defaults to `HOLD_INSUFFICIENT_DATA`

## Auditability

The `rule_path` field ensures:

1. **Transparency**: Users know exactly why a rating was assigned
2. **Reproducibility**: Same inputs always produce same `rule_path`
3. **Debugging**: Easy to trace rating logic issues
