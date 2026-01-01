# Design Doc: Hybrid Collection Strategy (Phase 1)

## Context
We require 20 years of historical financial data for AEX companies. Scraping (Yahoo) is fragile and shallow (4 years). FMP (API) offers deep history but has a strict 250 calls/day limit on the free tier.

## Core Mandate
**Primary Sources Only:** Yahoo Finance + Financial Modeling Prep (FMP).
**Allowed Auxiliary:** European Central Bank (ECB) for FX rates *only*.
**Scope Limit:** If data is missing in these sources, record as `not_found`. Direct ingestion of official IFRS filings is **out of scope** for Phase 1.

## Strategy: The "Hybrid" Split

To maximize the free tier, we split responsibilities by data type and volatility:

| Data Type | Provider | Method | Cost (Calls) | Frequency |
| :--- | :--- | :--- | :--- | :--- |
| **Valuation** | Yahoo Finance | Scraping | 0 | Daily |
| **Financials** | FMP | API | ~3 / co | Quarterly |
| **FX Rates** | ECB | XML/CSV | 0 | Daily |

### 1. Valuation (Yahoo)
- **Metrics:** Market Cap, P/E, Enterprise Value.
- **Why:** High volatility requiring daily updates. FMP charges credits for real-time; Yahoo scraping is free and sufficient for current snapshots.

### 2. Financials (FMP)
- **Metrics:** Income Statement, Balance Sheet, Cash Flow.
- **Why:** Complex, structured historical data (20+ years). FMP provides this "as reported" via API.
- **Optimization:** We explicitly **ignore** FMP's valuation endpoints to save credits.

### 3. FX Normalization (ECB)
- **Metrics:** EUR/USD daily reference rates (Historical + Daily).
- **Why:** AEX companies may report in USD or EUR. To provide a normalized 20-year view, we need official closing rates.
- **Source:** ECB Statistical Data Warehouse (SDMX/CSV). Public, free, no auth required.

## Implementation Plan

### Clients and Adapters
1.  **`YahooFinanceAdapter`** (Existing):
    - Focus: `valuation.*`
    - Status: Scraping HTML tables.

2.  **`FmpClient`** (New):
    - Location: `yii/src/clients/FmpClient.php`
    - Config: `FMP_API_KEY` in `.env`.
    - Logic: HTTP calls, retries/timeouts, surface non-2xx errors (no silent failures).

3.  **`FmpAdapter`** (New):
    - Focus: `financials.*`, `quarters.*`
    - Logic: Map FMP JSON to DTOs and DataPoints; record provenance for every metric or `not_found` when missing.
    - History: Fetch full history (limit=100 years) once per quarter per company.

4.  **`EcbClient`** (New):
    - Location: `yii/src/clients/EcbClient.php`
    - URL: `https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml`
    - Logic: HTTP fetch, error handling, and rate limiting.

5.  **`EcbAdapter`** (New):
    - Focus: `macro.fx_rates`
    - Logic: Map ECB XML/CSV to DataPoints; record provenance for every datapoint.
    - Cache: Store snapshots under `data/cache/ecb/` (approved project data folder).

### Orchestration
- **Chain Order:** `FmpAdapter` -> `YahooFinanceAdapter`.
- **DI Registration:** Register `FmpClient`, `FmpAdapter`, `EcbClient`, and `EcbAdapter` in `yii/config/container.php`.
- **Fallbacks:**
    - If FMP fails (limit reached/down) -> Log error and record `not_found` for each affected metric. **Do not** fallback to Yahoo for financials (formatting mismatch).
    - If Yahoo fails -> Record `not_found`.

### Rate Limiting
- Apply `enforce-rate-limit` for `financialmodelingprep.com` and `ecb.europa.eu` to stay within source policies.

## Provenance Rules
- **Valuation:** `source: yahoo_finance`, `method: scrape`
- **Financials:** `source: fmp`, `method: api`
- **FX:** `source: ecb`, `method: file_download`
- **Normalized Metrics:** Must retain raw source references and record mapping inputs (raw value + source) alongside normalized values.

## Failure Protocol
If a metric exists in official filings but is missing from FMP/Yahoo:
1.  **Record `not_found`**.
2.  Do **not** attempt to parse PDF/XBRL filings in Phase 1.

## Testing
- **Fixture Tests (Adapters):** Use real FMP JSON and ECB XML snapshots to verify mapping.
- **Unit Tests:** Validate DTO construction, provenance fields, and `not_found` handling.
- **Fixture Location:** Store snapshots under `tests/fixtures/adapters/`.
