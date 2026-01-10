# Glossary

Key terms used throughout AIMM documentation.

## Pipeline Terms

### Industry Set

The companies assigned to an industry configuration. Analysis uses the full set to compute group averages and rankings.

### Group Average

Aggregate metrics calculated across the industry set. Used to measure valuation gaps.

### Company Dossier

The persistent, database-backed record of a company's financial data.

### Analysis Context

The in-memory context for industry analysis, built directly from the Company Dossier.
The `IndustryAnalysisContext` DTO contains companies (as `CompanyData` objects),
macro data, and metadata needed for the analysis phase. It replaces the older
`IndustryDataPack` pattern.

### RankedReportDTO

Analyzed data ready for rendering (Phase 2 output). Contains ranked company analyses, group averages, and metadata used for PDF generation.

### Gate

A validation checkpoint between phases. Gates ensure data quality before proceeding to the next phase.

- **Collection Gate**: After Phase 1, before Phase 2
- **Analysis Gate**: After Phase 2, before Phase 3

## Financial Terms

### Valuation Gap

Percentage difference between a company and the group average. A positive gap indicates the company is "cheaper" than the industry average.

### LTM

Last Twelve Months. A trailing metric covering the most recent 12-month period, regardless of fiscal year boundaries.

### FY

Fiscal Year. The company's financial reporting year, which may not align with the calendar year.

### Forward P/E (fwd_pe)

Price-to-earnings ratio based on estimated future earnings. Lower generally indicates cheaper valuation.

### EV/EBITDA (ev_ebitda)

Enterprise Value to EBITDA ratio. A valuation metric that accounts for debt. Lower generally indicates cheaper valuation.

### FCF Yield (fcf_yield)

Free Cash Flow yield. Higher indicates the company generates more cash relative to its market cap.

### Dividend Yield (div_yield)

Annual dividend as a percentage of stock price. Higher indicates more income return to shareholders.

## Data Terms

### Provenance

Source attribution for a datapoint (URL, timestamp, method). Every collected value must have provenance.

### Required Datapoint

A metric that must have a value for collection to succeed. Missing required datapoints cause gate failure.

### Nullable Datapoint

A metric that may have a `null` value, but must document attempted sources if not found.

### Source Locator

Information about where a value was found in the source document (CSS selector, JSON path, etc.).

## Rating Terms

### Rating

The investment recommendation: BUY, HOLD, or SELL.

### Rule Path

The specific decision branch that produced a rating. Ensures auditability and reproducibility.

### Fundamentals

Assessment of company's operational health: Improving, Mixed, or Deteriorating.

### Risk

Assessment of investment risk: Acceptable, Elevated, or Unacceptable.

## Technical Terms

### Handler

A PHP class that performs one concrete application action end-to-end. Replaces generic "service" classes.

### Adapter

A PHP class that maps external response formats to internal DTOs. Isolates external format changes.

### Transformer

A PHP class that converts data from one shape to another. Replaces "helper" utilities.

### industry_id

The machine identifier for an industry config. Used in CLI commands, file paths, and artifact folders.

Example: `integrated_oil_gas`
