# Implementation Plan (Codex): Analyze All Eligibility State

**Project:** AIMM (Admin UI)  
**Area:** Industry detail page (`admin/industry/{slug}`)  
**Status:** Planned  
**Last updated:** 2026-01-10  

---

## 1. Problem Statement

On the industry detail page (example: `/admin/industry/us-tech-giants`), the **Analyze All** action should:
- Render as **"Analyse"** (per product requirement)
- Be grayed out and non-clickable when:
  1) No data has been collected yet
  2) Not all company dossiers have **Gate Passed**
- Provide a tooltip explaining the disabled state

---

## 2. Requirements & Constraints

- Use existing admin UI design tokens and patterns (`yii/web/css/tokens.css`, `yii/web/css/admin.css`).
- Follow BEM class naming and avoid inline styles (per `Frontend Design` skill).
- Keep controllers thin; use a handler/query for eligibility logic.
- No JavaScript framework; vanilla JS only if needed.
- Tooltip should be accessible (keyboard focus + `aria-disabled`).

---

## 3. Current State (Repo)

- **Analyze All** button lives in `yii/src/views/industry/view.php`.
- `IndustryController::actionView` passes `$industry`, `$companies`, `$runs`.
- Collection run data is available via `CollectionRunRepository::listByIndustry`.
- No existing disabled button style in `yii/web/css/admin.css`.
- Tooltip precedent exists via `title` attribute on a disabled button in `yii/src/views/data-source/view.php`.

---

## 4. Data Definition (To Confirm)

**No data collected yet**  
Preferred signal:
- No completed collection runs for the industry **OR**
- All companies have null dossier timestamps (`financials_collected_at`, `valuation_collected_at`, `quarters_collected_at`)

**All company dossiers have Gate Passed**  
Preferred signal (choose one):
- A) Use latest completed collection run and require zero per-company gate errors (from `collection_error` table by ticker)
- B) Introduce/consume a per-company dossier gate status if it already exists in dossier tables

If unclear, confirm the authoritative source with product before coding.

---

## 5. Phased Implementation Plan

### Phase 0 — Discovery & Definition
0.1 Confirm the exact meaning of **"no data collected yet"** (runs vs dossier timestamps).  
0.2 Confirm where **company dossier Gate Passed** is stored (collection errors vs dossier schema).  
0.3 ~~Confirm tooltip copy and whether multiple reasons should be combined or prioritized.~~
   - **Resolved:** Primary: "No data collected" / Secondary: "Data not complete"  

### Phase 1 — Eligibility Query/Handler
1.1 Add a new query/handler (e.g., `yii/src/queries/IndustryAnalysisEligibilityQuery.php`) that returns:
- `hasCollectedData` (bool)
- `allDossiersGatePassed` (bool)
- `disabledReason` (string|null)
1.2 Implement logic using existing repositories/queries:
- `CollectionRunRepository` for latest run(s)
- `CompanyQuery` for dossier timestamps
- Optional: new query for per-company gate pass based on `collection_error` by ticker
1.3 Add unit tests for the eligibility logic (new query/handler tests in `yii/tests/unit/queries/`).

### Phase 2 — Controller Wiring
2.1 In `IndustryController::actionView`, request eligibility status from the query/handler.  
2.2 Pass eligibility data to the view (`$analysisEligibility` or similar).  

### Phase 3 — View & UX Updates
3.1 Update `yii/src/views/industry/view.php`:
- Replace label with `"Analyse"`.
- Render the button in all eligible states (active + >=2 companies), but apply disabled UI + tooltip when needed.
- Use `disabled`, `aria-disabled="true"`, and a tooltip `title` on a wrapping element if needed (disabled buttons may not show tooltips).
3.2 If multiple disable reasons apply, show the highest priority reason:
   - Priority 1: "No data collected"
   - Priority 2: "Data not complete"

### Phase 4 — Styling
4.1 Add a reusable disabled button modifier in `yii/web/css/admin.css`:
- Example: `.btn--disabled` (greyed, `cursor: not-allowed`, reduced opacity)
4.2 Ensure color values use tokens and align with admin UI palette.

### Phase 5 — Verification
5.1 Manual checks:
- No runs / no dossier data → disabled + tooltip
- Some dossiers fail gate → disabled + tooltip
- All dossiers pass gate → enabled
5.2 Run relevant unit tests for eligibility query/handler.

---

## 6. Acceptance Criteria

- **Analyze All** label shows exactly `"Analyse"` on industry detail pages.
- Button is disabled with a tooltip when:
  - No data collected yet
  - Not all company dossiers have Gate Passed
- Button remains enabled when all required data is present and passed.
- No inline styles or hardcoded colors added.
- Eligibility logic is centralized and unit tested.

