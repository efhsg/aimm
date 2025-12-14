# MoneyMonkey Skills Index

Skills are atomic, executable capabilities with defined inputs, outputs, and completion criteria.

## System Overview

```
docs/
├── RULES.md              # Global guardrails (read first)
└── skills/
    ├── index.md          # This file (skill registry)
    ├── meta/             # Bootstrap/infrastructure (run once)
    ├── collection/       # Phase 1 skills
    ├── analysis/         # Phase 2 skills
    ├── rendering/        # Phase 3 skills
    └── shared/           # Cross-phase runtime skills
```

## How to Use

1. **Read `RULES.md`** — global conventions apply to everything
2. **Scan this index** — find relevant skills for your task
3. **Load only needed skills** — minimize context
4. **Implement following contracts** — inputs, outputs, DoD
5. **Create skills for gaps** — if behavior isn't covered, write a skill

## Collection Skills

Phase 1: Gathering financial data from sources.

| Skill | Description |
|-------|-------------|
| [collect-datapoint](collection/collect-datapoint.md) | Collect a single datapoint from prioritized sources with full provenance. Use for ONE specific metric. |
| [collect-company](collection/collect-company.md) | Collect all datapoints for a single company (valuation, financials, quarters). Orchestrates multiple collect-datapoint calls. |
| [collect-macro](collection/collect-macro.md) | Collect macro/market datapoints (commodity prices, margin proxies) for an industry. |
| [adapt-source-response](collection/adapt-source-response.md) | Parse a fetched response (HTML/JSON) into structured datapoints using source-specific adapters. |
| [build-source-candidates](collection/build-source-candidates.md) | Generate prioritized list of source URLs for a ticker and datapoint type. |
| [validate-collection-gate](collection/validate-collection-gate.md) | Validate an IndustryDataPack after collection. Gate between Phase 1 and Phase 2. |
| [enforce-rate-limit](collection/enforce-rate-limit.md) | Manage request pacing per domain to avoid blocks and respect source policies. |

## Analysis Skills

Phase 2: Computing metrics and generating ratings.

| Skill | Description |
|-------|-------------|
| calculate-peer-average | Calculate average metrics across peer companies. *(not yet written)* |
| calculate-valuation-gap | Compute percentage gap between focal and peer average. *(not yet written)* |
| determine-rating | Apply rating rules to produce BUY/HOLD/SELL. *(not yet written)* |
| validate-analysis-gate | Validate ReportDTO before rendering. *(not yet written)* |

## Rendering Skills

Phase 3: Generating PDF reports.

| Skill | Description |
|-------|-------------|
| render-pdf | Invoke Python renderer with ReportDTO. *(not yet written)* |
| generate-chart | Create matplotlib chart from data. *(not yet written)* |

## Meta Skills

Project setup and infrastructure. Run once to bootstrap, not during pipeline execution.

| Skill | Description |
|-------|-------------|
| [setup-project](meta/setup-project.md) | Bootstrap Yii2/Python project from scratch. Directory structure, dependencies, configuration. |
| [setup-git-remote](meta/setup-git-remote.md) | Configure Git `origin` remote and (optionally) push initial branch. |
| [upgrade-php-version](meta/upgrade-php-version.md) | Upgrade PHP version used by the Yii2 runtime (Docker + Composer constraints) and validate the stack. |
| [create-migration](meta/create-migration.md) | Create Yii2 database migrations. Tables for collection logs, rate limiting, job queue. |
| [review-and-improve-skill](meta/review-and-improve-skill.md) | Review an existing skill doc and rewrite it with tighter contracts and actionable DoD/tests. |
| [add-docs-readme](meta/add-docs-readme.md) | Add or update `docs/README.md` to describe documentation entry points and contribution rules. |
| [add-root-readme](meta/add-root-readme.md) | Add or update the repository root `README.md` to describe the application and local dev setup. |

## Shared Skills

Runtime utilities used across multiple phases.

| Skill | Description |
|-------|-------------|
| [record-provenance](shared/record-provenance.md) | Build a complete DataPoint with provenance from raw extraction data. |
| [record-not-found](shared/record-not-found.md) | Build a DataPoint for data that could not be found after exhausting sources. |

## Skill Relationships

```
CollectIndustryHandler
├── collect-macro
│   ├── build-source-candidates
│   ├── collect-datapoint
│   │   ├── enforce-rate-limit
│   │   ├── adapt-source-response
│   │   ├── record-provenance (found)
│   │   └── record-not-found (not found)
│   └── record-provenance (derived)
├── collect-company (×N)
│   ├── build-source-candidates
│   └── collect-datapoint (×M metrics)
│       └── ... (same as above)
└── validate-collection-gate
```

## Naming Conventions

- **collect-*** — Fetches data from external sources
- **adapt-*** — Transforms external format to internal
- **build-*** — Constructs objects or lists
- **calculate-*** — Computes derived values
- **validate-*** — Checks data against rules
- **record-*** — Creates audit/provenance records
- **render-*** — Produces output artifacts
- **determine-*** — Applies business rules

## Adding New Skills

1. Identify atomic operation with clear input/output
2. Create `{phase}/{skill-name}.md`
3. Include: frontmatter (name, description), interface, input/output DTOs, algorithm, DoD
4. Add to this index
5. Update relationship diagram if needed
