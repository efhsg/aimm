# AIMM Site Layout

## Purpose
Document the complete AIMM equity intelligence pipeline for internal operators, emphasizing data quality, provenance, and the three-phase flow from collection to PDF rendering.

## Audience
- **Primary:** Internal operators (engineering, data, and tooling users).
- **Future:** External stakeholders (possible later).

## Layout Variants
Two versions are defined to compare scope.

### Version A: Pipeline and Artifacts Only
**Global Navigation**
- **Primary**
  - Overview
  - Pipeline
  - Architecture
  - Data Quality
  - Configuration
  - CLI Usage
  - Tech Stack
  - Glossary
- **Secondary**
  - Outputs (Artifacts)
  - Directory Structure
  - Validation Gates
  - Rating Logic

**Page Map**
- `Overview` (root)
- `Pipeline` (Phase 1, Phase 2, Phase 3)
- `Architecture`
- `Data Quality`
- `Configuration`
- `CLI Usage`
- `Tech Stack`
- `Glossary`
- `Outputs` (Artifacts and Paths)
- `Directory Structure`

**Footer**
- Quick links: Pipeline, Data Quality, CLI Usage, Tech Stack, Glossary.
- Version and last updated date.

### Version B: Pipeline + Admin UI
**Global Navigation**
- **Primary**
  - Overview
  - Pipeline
  - Architecture
  - Data Quality
  - Configuration
  - CLI Usage
  - Tech Stack
  - Admin UI
  - Glossary
- **Secondary**
  - Outputs (Artifacts)
  - Directory Structure
  - Validation Gates
  - Rating Logic
  - Admin UI: Peer Groups
  - Admin UI: Collection Policies
  - Admin UI: Collection Runs

**Page Map**
- `Overview` (root)
- `Pipeline` (Phase 1, Phase 2, Phase 3)
- `Architecture`
- `Data Quality`
- `Configuration`
- `CLI Usage`
- `Tech Stack`
- `Admin UI`
  - `Admin UI Overview`
  - `Peer Groups`
  - `Collection Policies`
  - `Collection Runs`
- `Glossary`
- `Outputs` (Artifacts and Paths)
- `Directory Structure`

**Footer**
- Quick links: Pipeline, Data Quality, CLI Usage, Tech Stack, Admin UI, Glossary.
- Version and last updated date.

---

## Comparison Matrix
| Dimension | Version A: Pipeline Only | Version B: Pipeline + Admin UI |
| --- | --- | --- |
| Primary audience | Internal operators | Internal operators (expanded scope) |
| Primary scope | Pipeline docs and artifacts | Pipeline docs plus admin UI ops |
| Navigation size | Smaller | Larger |
| Admin UI coverage | None | Overview + 3 entity pages |
| Maintenance cost | Lower | Higher |
| Best for | Core pipeline onboarding | Ops workflow + pipeline context |

## Shared Page Layouts (Versions A and B)

### 1) Overview (Home)
**Goal:** Explain what AIMM is and why it exists.
- **Hero**
  - Title: AIMM
  - Subtitle: Equity intelligence pipeline for smarter investment decisions.
  - Key principle: Data quality over speed.
- **At-a-Glance**
  - Three phases: Collect, Analyze, Render.
  - Output: Institutional-grade PDF report.
- **Terminology Primer**
  - Industry vs Sector definitions.
  - `industry_id` usage.
- **CTA Strip (Docs-first)**
  - Links to Pipeline, Architecture, CLI Usage.

### 2) Pipeline
**Goal:** Show the three-phase system end-to-end.
- **Phase 1: Collect**
  - Inputs: Industry config JSON.
  - Outputs: IndustryDataPack JSON.
  - Macro + company collectors.
  - Collection Gate (schema, required data, freshness).
- **Phase 2: Analyze**
  - Inputs: IndustryDataPack + focal ticker + peers.
  - Outputs: ReportDTO JSON.
  - Deterministic calculations only.
  - Analysis Gate (schema, recomputation, rating consistency).
- **Phase 3: Render**
  - Inputs: ReportDTO.
  - Outputs: report.pdf.
  - Python renderer, no business logic.
- **Flow Diagram Section**
  - Inline diagram of the three phases (from project description).

### 3) Architecture
**Goal:** Explain the handler-based architecture and module boundaries.
- **Why Handlers (not Services)**
  - Orchestration via handlers.
  - Commands as entry points.
- **Layer Responsibilities**
  - Handlers, Queries, Validators, Transformers, Factories, DTOs, Clients, Adapters.
- **Module Criteria**
  - When to extract a module.
- **Folder Decision Guide**
  - Table mapping responsibilities to folders.

### 4) Data Quality
**Goal:** Make provenance and validation rules explicit.
- **Datapoint Provenance**
  - Required fields and example payload.
- **Nullable vs Required Datapoints**
  - `not_found` handling and attempted sources.
- **Validation Gates**
  - Collection Gate checks.
  - Analysis Gate checks.
- **Error Handling**
  - GateResult structure.
  - Exit codes and meanings.

### 5) Configuration
**Goal:** Explain how industry configs and schemas drive the system.
- **Industry Config**
  - `id`, `name`, `sector`.
  - Companies list.
  - Macro requirements.
  - Data requirements (valuation, annual, quarter, operational).
- **Schema Overview**
  - industry-config schema
  - industry-datapack schema
  - report-dto schema
- **Application Params**
  - `schemaPath`, `industriesPath`, `datapacksPath`, `pythonRendererPath`.

### 6) CLI Usage
**Goal:** Provide the commands for the three phases.
- **Collect**
  - `yii collect/list`
  - `yii collect/industry {industry_id}`
- **Analyze**
  - `yii analyze/report --datapack=... --focal=... --peers=...`
- **Render**
  - `yii render/pdf --dto=...`
- **Full Pipeline**
  - `yii pipeline/run {industry_id} --focal=... --peers=...`

### 7) Tech Stack
**Goal:** Document the core technologies and constraints.
- **Orchestration:** Yii2 (PHP 8.2+)
- **Rendering:** Python 3.11+ with ReportLab + matplotlib
- **Schema Validation:** JSON Schema draft-07 (opis/json-schema)
- **Process:** Symfony Process
- **Queue:** yii2-queue (optional)
- **Dependencies Summary**
  - PHP and Python dependency lists (from project description).

### 8) Glossary
**Goal:** Define core terms used throughout the system.
- Focal company
- Peers
- DataPack
- ReportDTO
- Gate
- Valuation gap
- Provenance
- LTM, FY

### 9) Outputs (Artifacts)
**Goal:** Show artifact paths and file types.
- **Artifacts**
  - `runtime/datapacks/{industry_id}/{uuid}/datapack.json`
  - `runtime/datapacks/{industry_id}/{uuid}/validation.json`
  - `runtime/datapacks/{industry_id}/{uuid}/report-dto.json`
  - `runtime/datapacks/{industry_id}/{uuid}/report.pdf`
- **Naming Rules**
  - `aimm-*` for logs and artifacts.

### 10) Directory Structure
**Goal:** Provide a navigable map of the repository layout.
- **Top-Level Map**
  - `commands/`, `handlers/`, `queries/`, `validators/`, `transformers/`, `factories/`, `dto/`, `clients/`, `adapters/`, `enums/`, `exceptions/`, `jobs/`, `python-renderer/`, `tests/`.
- **Dropped Types**
  - `services/`, `helpers/`, `components/`, `utils/`.

---

## Admin UI Page Layouts (Version B Only)
These pages document the internal admin interface for pipeline inputs and run monitoring.

### 11) Admin UI Overview
**Goal:** Provide a concise map of operational entities and how they connect to the pipeline.
- **Entities**
  - Peer Groups (focal + peers)
  - Collection Policies (data requirements + macro rules)
  - Collection Runs (execution history + outcomes)
- **Navigation**
  - Links to Peer Groups, Collection Policies, Collection Runs.
- **How It Maps to the Pipeline**
  - Policy defines requirements.
  - Peer Group defines the focal company and peers.
  - Run produces datapack, validation, DTO, and PDF artifacts.

### 12) Peer Groups
**Goal:** Document how operators manage peer sets and run collection.
- **Index**
  - Search, status filters, and run status badges.
  - Primary actions: View, Edit, Run collection.
- **Detail View**
  - Group metadata and status.
  - Members table with focal indicator.
  - Recent runs table with status and issues.
- **Create/Update**
  - Name, slug, sector, description, policy link.

### 13) Collection Policies
**Goal:** Document the configuration definitions that drive collection.
- **Index**
  - Policy list with sector defaults and descriptions.
- **Detail View**
  - Macro requirements.
  - Data requirements (valuation, annual, quarter, operational).
- **Create/Update**
  - Core identifiers and numeric requirements.
  - JSON-based metric lists.

### 14) Collection Runs
**Goal:** Show operational run history and diagnostics.
- **Index**
  - Status filter, search, and pagination.
  - Run list with status badges.
- **Detail View**
  - Run metadata (industry/peer group, started/completed, duration).
  - Output artifacts and counts.
  - Errors and warnings tables.
