# Implementation Plan (Codex): Industry Config Management UI

**Project:** AIMM (Equity Intelligence Pipeline)  
**Area:** Web (Yii2)  
**Status:** Implemented  
**Last updated:** 2025-12-31  

---

## 1. Problem Statement

We need a secure web interface to manage the `industry_config` table:

- View a list of configured industries
- View a single industry config (including `config_json`)
- Create a new industry config
- Edit an existing industry config
- Toggle `is_active` (enable/disable collection eligibility)

This UI is administrative and must not be publicly accessible.

---

## 2. Goals / Non-Goals

### Goals

1. **CRUD + Toggle** for `industry_config` with a usable server-rendered UI.
2. **Strict validation** for `config_json`:
   - valid JSON
   - valid against `yii/config/schemas/industry-config.schema.json`
   - semantic constraint: `config_json.id` must match `industry_config.industry_id` (per `docs/user/collection-guide.md`)
3. **Safe toggle behavior**: disabling/enabling must work even if `config_json` is currently invalid.
4. **Administrative auditing**: store `created_by` and `updated_by` to track who performed mutations.
5. **Admin-only access** with explicit authentication and authorization.
6. **Audit-friendly logging** of mutations without leaking secrets or logging full JSON blobs.

### Non-Goals (for MVP)

- A full user system / RBAC UI.
- A SPA (React/Vue) or GraphQL endpoint.
- A schema-driven form builder for editing JSON as individual fields.
- Allowing `industry_id` changes after creation (recommended immutable).

---

## 3. Current State (Repo Analysis)

### Backend & Data

- Database is **MySQL** (`yii/config/db.php`), so `config_json` is **TEXT**, not a native JSON type.
- `industry_config` exists and is used by the collection pipeline (note there are **two similarly named query classes** with different responsibilities):
  - `app\models\IndustryConfig` ActiveRecord exists; `IndustryConfig::find()` returns `app\models\query\IndustryConfigQuery` (ActiveQuery scopes like `active()`, `inactive()`, `byIndustryId()`).
  - `app\queries\IndustryConfigQuery` is a domain query that loads records, validates `config_json` via `SchemaValidatorInterface`, and maps to `app\dto\IndustryConfig` for collection.
  - JSON Schema lives at `yii/config/schemas/industry-config.schema.json`.
- The management UI must be able to **view and disable invalid configs**, so list/detail screens should not depend on the domain query (which throws on invalid JSON/schema).

### Web App

- Web entry exists (`yii/web/index.php`) and routes to `app\controllers\HealthController` only.
- No `user`/session/auth components are configured for the web app (`yii/config/web.php`).
- There is a design-system token bundle under `docs/design/frontend/style/` (CSS variables, SCSS, brand guide).

### Architecture Constraints (Project Rules)

- No business logic in controllers; delegate to **handlers** (`docs/rules/coding-standards.md`).
- Use specific folders; do not introduce catch-all folders, and never create banned folders (`docs/rules/architecture.md`).
- Security requirements: scope enforcement, no secrets in code, don’t log sensitive values (`docs/rules/security.md`).
- Testing: unit tests for validators/handlers; integration tests where needed (`docs/rules/testing.md`).

---

## 4. Open Questions (Need Confirmation)

1. **Authentication strategy**
   - A) Nginx Basic Auth only (fastest, infrastructure enforced)
   - B) App-level env-driven Basic Auth filter (portable; still works without nginx auth)
   - C) Token-based header auth (simple, but riskier operationally; tokens leak easily)

2. **Source of truth for `name`**
   - A) Require `industry_config.name === config_json.name` (strict consistency)
   - B) Derive `industry_config.name` from `config_json.name` on save (single source of truth)

3. **Draft handling**
   - A) Reject saving invalid `config_json` (recommended)
   - B) Allow saving invalid JSON/schema as a draft (requires additional state, UI and behavior rules)

4. **UI scope**
   - A) Server-rendered MVC only (recommended for MVP)
   - B) Add JSON REST endpoints in parallel (future-facing, more surface area)

5. **Audit field semantics**
   - A) Store actor identity as username string (recommended for MVP; fits env-driven Basic Auth; no user table required)
   - B) Store actor identity as integer FK (requires a `user` table/identity model; add FK only if it exists)

---

## 5. Proposed Architecture

### 5.1 Backend Boundaries

**Reads**
- Keep list/detail reads thin and predictable (ActiveRecord or dedicated `queries/` class returning records/arrays).

**Writes**
- Create/update/toggle are implemented as **handlers** with explicit input DTOs.
- Handlers own transactions, validation calls, and structured logging.
- Handlers also stamp audit fields (`created_by`, `updated_by`) based on authenticated actor identity.

**Validation**
- Implement a dedicated validator for `config_json`:
  - JSON parse validation (human-friendly error message)
  - JSON schema validation (reuse `SchemaValidatorInterface`)
  - semantic checks (`config_json.id` equals `industry_id`, plus name rule from Open Question #2)

### 5.2 Routes (Server-Rendered MVC MVP)

Note: These routes assume `urlManager` is configured for pretty URLs; otherwise they will be accessed via query routes like `/index.php?r=industry-config%2Findex`.

| Method | Route | Purpose | Notes |
|---:|---|---|---|
| GET | `/industry-config/index` | List configs | filters: active/inactive, search |
| GET | `/industry-config/view?industry_id=...` | Detail view | show raw JSON (pretty-printed) |
| GET/POST | `/industry-config/create` | Create | server validation on submit |
| GET/POST | `/industry-config/update?industry_id=...` | Update | `industry_id` read-only |
| POST | `/industry-config/toggle?industry_id=...` | Toggle active | must not require valid JSON |
| POST (optional) | `/industry-config/validate-json` | Validate JSON | returns parse + schema errors |

### 5.3 UI / Views

**Views location**
- Verify Yii view path resolution (Phase 0). With current config, `@app` is set to `yii/src`, so the default view path is expected to be `yii/src/views/` unless overridden.

**Pages**
- Index: table list + actions (view/edit/toggle), “Create” CTA.
- View: read-only details + “Edit” + “Toggle”.
- Create/Update: shared form partial.

**JSON editing**
- MVP: textarea + buttons:
  - “Format JSON” (client-side `JSON.parse`/`JSON.stringify`)
  - “Validate” (optional AJAX call to backend to show schema errors without submit)
- Future: integrate an editor (CodeMirror/Monaco) only if needed; avoid network/CDN dependency in early iterations.

**Styling**
- Use `docs/design/frontend/style/tokens.css` to provide consistent typography/colors without introducing a full CSS framework.

---

## 6. Security Design

### 6.1 Authentication & Authorization (MVP)

**Recommendation:** App-level env-driven Basic Auth filter (portable) + optional nginx auth defense-in-depth.

Implementation concept:
- Add a small auth filter/middleware applied to all `IndustryConfigController` actions.
- Verify credentials against environment variables (no DB).
- Return `401` with `WWW-Authenticate` challenge on failure.

Authorization:
- For MVP, treat “authenticated admin” as a single role.
- Enforce in controller behavior **and** re-check in write handlers (“scope enforcement”).

### 6.2 CSRF / Request Safety

- Keep Yii CSRF enabled for all POST actions.
- Toggle action must be POST-only (no GET toggles).
- If using AJAX (toggle/validate), include the CSRF token in the request (header/param) using Yii’s CSRF meta tags/helpers.

### 6.3 Logging & Secrets

- Log: action name, `industry_id`, actor identity, success/failure, and validation error summaries.
- Do **not** log `config_json` (full payload) and do not log credentials/tokens.

---

## 7. Validation Rules (Concrete)

### 7.1 `industry_id`

- Required on create
- Max length 64
- Unique
- Allowed pattern should match schema `id` constraints (current schema pattern: `^[a-z0-9_-]+$`)
- Immutable after create (recommended)

### 7.2 `config_json`

Validation on create/update:
1. Valid JSON (parseable)
2. Valid against `industry-config.schema.json` via `SchemaValidatorInterface`
3. Semantic: decoded JSON object field `id` must equal `industry_id`
4. Name rule (Open Question #2)

Notes:
- The schema requires `companies` with `minItems: 1`; empty `companies` arrays will fail schema validation.
- The schema also requires keys beyond `id/name` (e.g. `sector`, `macro_requirements`, `data_requirements`), so “minimal” test/UI examples must be schema-compliant.

Validation on toggle:
- Must **not** require re-validation of JSON.

---

## 8. Testing Strategy

### Unit Tests (Required)

- `IndustryConfigJsonValidator`:
  - rejects invalid JSON
  - rejects schema violations (via `SchemaValidatorInterface`)
  - rejects `config_json.id` mismatch
  - accepts valid config
- Handlers:
  - create success + duplicate industry_id
  - update success + invalid JSON/schema
  - toggle works even if config invalid
  - audit stamping (created_by/updated_by set as expected)

### Integration Tests (Recommended)

- Handler integration tests with DB-backed persistence (Codeception + Yii2 module), focusing on:
  - transaction behavior
  - unique constraint handling
  - toggle behavior independent of JSON validity

### Out of Scope

- No tests for Yii view templates or widgets (per `docs/rules/testing.md`).

---

## 9. Implementation Plan (Agent-Ready, Phased)

### Phase 0 — Confirm Requirements & Security

0.1 Confirm the Open Questions in Section 4.  
0.2 Verify Yii view path resolution (`yii/src/views` vs `yii/views`) and decide whether to rely on defaults or set an explicit `viewPath`.  
0.3 Confirm whether an API is explicitly required now, or MVC-only MVP is acceptable.  
0.4 Confirm whether to enable pretty URLs; if yes, plan to add `urlManager` config and route rules in `yii/config/web.php`.  
0.5 If using app-level auth and flash messaging, add/configure the `session` component in `yii/config/web.php` with secure cookie settings.  

### Phase 1 — Validation Foundation

1.1 Add a migration to add `created_by` and `updated_by` to `industry_config` for admin tracking (MVP default: `VARCHAR(255) NULL` storing authenticated username; optional: `INT NULL` + FK only if a user table exists and is stable).  
1.2 Add a dedicated `config_json` validator in `yii/src/validators/` that uses `SchemaValidatorInterface`.  
1.3 Add semantic checks (`config_json.id` equals `industry_id`, plus decided `name` rule).  
1.4 Add scenario-based JSON+schema validation to `app\models\IndustryConfig` for create/update (today it only validates `config_json` as a generic string).  
1.5 Update/add unit tests knowing schema validation will make existing YAML-like placeholders (e.g. `companies: []`) and empty `companies` arrays fail.  

### Phase 2 — Write Handlers (Create/Update/Toggle)

2.1 Add DTOs for create/update/toggle requests (and list/detail responses) in `yii/src/dto/`, with `toArray(): array` on response DTOs to ease a future JSON API.  
2.2 Implement handlers in `yii/src/handlers/`:
- Create handler: validates + persists
- Update handler: validates + persists; `industry_id` immutable
- Toggle handler: flips `is_active` without forcing JSON validation
2.3 Implement the chosen `name` strategy in handlers:
- Option A: enforce `industry_config.name === config_json.name`
- Option B: derive and overwrite `industry_config.name` from `config_json.name` on save  
2.4 Add structured logging in handlers (no JSON payload logging).  
2.5 Stamp audit fields in handlers (`created_by` on create; `updated_by` on create/update/toggle) using the authenticated actor identity.  
2.6 Register handler definitions in `yii/config/container.php`.  
2.7 Add unit tests for handlers (happy path + key failures).  

### Phase 3 — Read Queries for UI

3.1 Add a small query class in `yii/src/queries/` for listing configs (filters + ordering), using ActiveRecord + `app\models\query\IndustryConfigQuery` scopes (not the domain query that throws on invalid JSON).  
3.2 Add a detail query method (load by `industry_id`, include inactive).  
3.3 Add minimal integration tests (optional) if query logic grows beyond trivial AR calls.  

### Phase 4 — Web Controller + Views (Server-Rendered)

4.0 Configure minimal web plumbing in `yii/config/web.php` as needed (per Phase 0 decisions): `urlManager` rules, `session`, and any required request/view settings.  
4.1 Add `IndustryConfigController` in `yii/src/controllers/` with thin actions calling queries/handlers.  
4.2 Add auth filter/behavior (env-driven Basic Auth or nginx enforcement, per Phase 0 decision).  
4.3 Add a minimal app layout under the decided view path:
- `layouts/main.php` wrapping all CRUD pages (basic navigation + consistent styling using `docs/design/frontend/style/tokens.css`).  
4.4 Add CRUD views under the decided view path:
- `industry-config/index.php`
- `industry-config/view.php`
- `industry-config/create.php`
- `industry-config/update.php`
- `industry-config/_form.php`
4.5 Add lightweight JS helper for JSON format/lint (no external dependencies) and include CSRF token on AJAX calls (toggle/validate).  
4.6 Add minimal CSS using `docs/design/frontend/style/tokens.css` (copy/link strategy decided in Phase 0).  

### Phase 5 — End-to-End Verification & Docs

5.1 Verify CRUD + toggle flows manually in Docker environment.  
5.2 Run targeted Codeception unit tests for new validators/handlers.  
5.3 Add a short admin usage doc (how to enable auth, where to access UI).  

---

## 10. Deliverables Checklist (Definition of Done)

- UI supports list/view/create/update/toggle for `industry_config`.
- `config_json` is validated as JSON + schema + semantic rules on create/update.
- Toggle works regardless of JSON validity.
- Admin access is enforced (not publicly accessible).
- `industry_config` includes `created_by` and `updated_by`, and handlers stamp them on create/update/toggle.
- New response DTOs implement `toArray(): array` for JSON-friendly serialization.
- Unit tests exist for new validator and handlers.
- No secrets committed; logs avoid sensitive payloads.
- No banned folders introduced.
