# AIMM Site Layout

## Purpose
Document the complete AIMM equity intelligence pipeline for internal operators, emphasizing data quality, provenance, and the three-phase flow from collection to PDF rendering.

## Audience
- **Primary:** Internal operators (engineering, data, and tooling users).
- **Future:** External stakeholders (possible later).

## Layout
Version B (Pipeline + Admin UI) is the active layout for the site.

**Deployment:** Layout is defined at build time via documentation generator config (e.g., VitePress sidebar config).

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
  - Admin UI: Industry Configs

**Page Map**
- `Overview` (root)
- `Pipeline` (Phase 1, Phase 2, Phase 3)
- `Architecture`
- `Data Quality`
- `Validation Gates`
- `Rating Logic`
- `Configuration`
- `CLI Usage`
- `Tech Stack`
- `Admin UI`
  - `Admin UI Overview`
  - `Peer Groups`
  - `Collection Policies`
  - `Collection Runs`
  - `Industry Configs`
- `Glossary`
- `Outputs` (Artifacts and Paths)
- `Directory Structure`

**Footer**
- Quick links: Pipeline, Data Quality, CLI Usage, Tech Stack, Admin UI, Glossary.
- Version and last updated date.

---

## Page Layouts

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
- Source: `docs/design/project-description.md` (Overview, Pipeline)

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
- Source: `docs/design/project-description.md` (Pipeline, CLI Commands)

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
- Source: `docs/design/project-description.md` (Directory Structure, Layer Responsibilities)

### 4) Data Quality
**Goal:** Make provenance and validation rules explicit.
- **Datapoint Provenance**
  - Required fields and example payload.
- **Typed Datapoints**
  - `DataPointNumber`, `DataPointMoney`, `DataPointPercent`, `DataPointRatio`, `DataPointUrl`.
- **Nullable vs Required Datapoints**
  - `not_found` handling and attempted sources.
- **Validation Gates**
  - Collection Gate checks.
  - Analysis Gate checks.
- **Error Handling**
  - GateResult structure.
  - Exit codes and meanings.
- Source: `docs/design/project-description.md` (Typed Datapoints, Nullable vs Required, Validation Gates, Gate Failures)

### 5) Validation Gates
**Goal:** Detail the gate checks between phases and how failures surface.
- **Collection Gate (after Phase 1)**
  - Schema compliance, required datapoints present, company coverage, macro freshness, minimum history.
- **Analysis Gate (after Phase 2)**
  - Schema compliance, recomputation checks (peer averages, valuation gap), rating rule path consistency.
- **Failure Output**
  - `GateResult` payload and exit codes.
- Source: `docs/design/project-description.md` (Validation Gates, Gate Failures)

### 6) Rating Logic
**Goal:** Explain the rule-based BUY/HOLD/SELL decision path.
- **Rule Path**
  - Deterministic branches with `rule_path` output.
- **Valuation Gap**
  - Composite gap from `fwd_pe`, `ev_ebitda`, `fcf_yield`, `div_yield` when at least two are present.
- Source: `docs/design/project-description.md` (Rating Logic, Valuation Gap Calculation)

### 7) Configuration
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
  - `pythonBinary`, `macroStalenessThresholdDays`, `renderTimeoutSeconds`.
- Source: `docs/design/project-description.md` (Industry Config, Application Parameters)

### 8) CLI Usage
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
- Source: `docs/design/project-description.md` (CLI Commands)

### 9) Tech Stack
**Goal:** Document the core technologies and constraints.
- **Orchestration:** Yii2 (PHP 8.2+)
- **Rendering:** Python 3.11+ with ReportLab + matplotlib
- **Schema Validation:** JSON Schema draft-07 (opis/json-schema)
- **Process:** Symfony Process
- **Queue:** yii2-queue (optional)
- **Dependencies Summary**
  - PHP and Python dependency lists (from project description).
- Source: `docs/design/project-description.md` (Dependencies)

### 10) Glossary
**Goal:** Define core terms used throughout the system.
- Focal company
- Peers
- DataPack
- ReportDTO
- Gate
- Valuation gap
- Provenance
- LTM, FY

### 11) Outputs (Artifacts)
**Goal:** Show artifact paths and file types.
- **Artifacts**
  - `runtime/datapacks/{industry_id}/{uuid}/datapack.json`
  - `runtime/datapacks/{industry_id}/{uuid}/validation.json`
  - `runtime/datapacks/{industry_id}/{uuid}/report-dto.json`
  - `runtime/datapacks/{industry_id}/{uuid}/report.pdf`
- **Naming Rules**
  - `aimm-*` for logs and artifacts.
- Source: `docs/design/project-description.md` (CLI Commands, Artifacts)

### 12) Directory Structure
**Goal:** Provide a navigable map of the repository layout.
- **Top-Level Map**
  - `commands/`, `handlers/`, `queries/`, `validators/`, `transformers/`, `factories/`, `dto/`, `clients/`, `adapters/`, `enums/`, `exceptions/`, `jobs/`, `config/`, `python-renderer/`, `runtime/`, `tests/`.
- **Dropped Types**
  - `services/`, `helpers/`, `components/`, `utils/`.
- Source: `docs/design/project-description.md` (Directory Structure)

---

## Admin UI Page Layouts
These pages document the internal admin interface for pipeline inputs and run monitoring.

### 13) Admin UI Overview
**Goal:** Provide a concise map of operational entities and how they connect to the pipeline.
- **Access Control**
  - Authentication: HTTP Basic Auth via `AdminAuthFilter`.
  - Credentials: Environment variables `ADMIN_USERNAME` and `ADMIN_PASSWORD`.
  - Authorization: All authenticated users have full access (no role-based restrictions).
  - Audit: All create/update operations log `created_by`/`updated_by` with username.
- **Entities**
  - Industry Configs (industry-level inputs)
  - Peer Groups (focal + peers)
  - Collection Policies (data requirements + macro rules)
  - Collection Runs (execution history + outcomes)
- **Navigation**
  - Links to Industry Configs, Peer Groups, Collection Policies, Collection Runs.
- **How It Maps to the Pipeline**
  - Industry Config defines the industry inputs for collection.
  - Policy defines requirements.
  - Peer Group defines the focal company and peers.
  - Run produces datapack, validation, DTO, and PDF artifacts.
- Source: `docs/design/web-collection-interface.md` (Security, Navigation), `docs/design/frontend/frontend_redesign.md` (Admin UI views), `docs/design/frontend/manage_industry_config.md` (Industry Configs UI)

### 14) Peer Groups
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
- Source: `docs/design/frontend/frontend_redesign.md` (Peer Groups views), `docs/design/web-collection-interface.md` (Peer Group wireframes)

### 15) Collection Policies
**Goal:** Document the configuration definitions that drive collection.
- **Index**
  - Policy list with sector defaults and descriptions.
- **Detail View**
  - Macro requirements.
  - Data requirements (valuation, annual, quarter, operational).
- **Create/Update**
  - Core identifiers and numeric requirements.
  - JSON-based metric lists.
- Source: `docs/design/frontend/frontend_redesign.md` (Collection Policies views)

### 16) Collection Runs
**Goal:** Show operational run history and diagnostics.
- **Index**
  - Status filter, search, and pagination.
  - Run list with status badges.
- **Detail View**
  - Run metadata (industry/peer group, started/completed, duration).
  - Output artifacts and counts.
  - Errors and warnings tables.
- Source: `docs/design/frontend/frontend_redesign.md` (Collection Runs views)

### 17) Industry Configs
**Goal:** Document how operators manage industry configuration records.
- **Index**
  - Filter/search, active/inactive toggle, Create CTA.
  - Show validation status and last updated metadata.
- **Detail View**
  - Read-only metadata and formatted `config_json`.
  - Actions: Edit, Toggle active.
- **Create/Update**
  - JSON editor textarea with format/validate actions.
  - `industry_id` immutable after create.
- **Access Control**
  - Admin-only via HTTP Basic Auth (env-driven credentials).
  - Audit fields: `created_by`, `updated_by`.
- Source: `docs/design/frontend/manage_industry_config.md`
