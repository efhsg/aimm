# Reference Documentation

API and architecture documentation for implemented AIMM components.

## Collection Handlers

| Component | Description |
|-----------|-------------|
| [collect-datapoint](collection/collect-datapoint.md) | Single datapoint collection with provenance |
| [collect-company](collection/collect-company.md) | Company data orchestration (valuation, financials, quarters) |
| [collect-macro](collection/collect-macro.md) | Macro/market data collection |
| [adapt-source-response](collection/adapt-source-response.md) | HTML/JSON parsing via adapters |
| [build-source-candidates](collection/build-source-candidates.md) | Source URL prioritization |
| [validate-collection-gate](collection/validate-collection-gate.md) | Gate validation between phases |
| [enforce-rate-limit](collection/enforce-rate-limit.md) | Rate limiting per domain |

## Shared Components

| Component | Description |
|-----------|-------------|
| [record-provenance](shared/record-provenance.md) | DataPoint construction with provenance |
| [record-not-found](shared/record-not-found.md) | Not-found DataPoint with attempted sources |
