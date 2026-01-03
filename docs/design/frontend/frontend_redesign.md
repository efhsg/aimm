# Frontend Redesign Specification: AIMM

## Objective
Redesign the AIMM (Equity Intelligence Pipeline) admin UI to match the AIMM Master Brand & UI System. The UI must feel institutional-grade: sober, data-dense, and reliable.
Reference: `docs/design/frontend/style/aimm-brand-guide-v1.3.html` (v1.3.1).

## 1. Design System Tokens (Strict Compliance)
Use tokens from `yii/web/css/tokens.css`. Do not hardcode colors or spacing.

### Colors
* **Primary:** `var(--brand-primary)` (`#1a4a55`) - headings, primary actions
* **Dark:** `var(--brand-dark)` (`#0d2a32`) - footer, dark surfaces
* **Accent:** `var(--brand-accent)` (`#A68248`) - premium or emphasis actions
* **Backgrounds:**
  * Page: `var(--bg-page)` (`#f0f4f5`)
  * Surface: `var(--bg-surface)` (`#ffffff`)
  * Elevated: `var(--bg-elevated)` (`#f8fafa`)
  * Muted: `var(--bg-muted)` (`#e6eef0`)
* **Semantic:**
  * Success: `var(--color-success)` (`#3b755f`)
  * Error: `var(--color-error)` (`#a5443f`)
  * Warning: `var(--color-warning)` (`#b8860b`)
  * Info: `var(--color-info)` (`#1a4a55`)
* **Text:**
  * Primary: `var(--text-primary)` (`#1a3540`)
  * Secondary: `var(--text-secondary)` (`#5a7a88`)
  * Tertiary: `var(--text-tertiary)` (`#8a9da6`, decorative only)
  * Inverse: `var(--text-inverse)` (`#ffffff`, for dark surfaces)
  * Mono: `var(--font-mono)` for all tickers and numeric data

### Layout & Spacing
* **Container:** `admin-container` (max-width `var(--container-xl)`).
* **Spacing:** `var(--space-1)` through `var(--space-12)`.

## 2. CSS Architecture (BEM)
Use BEM classes from `yii/web/css/admin.css`. Keep component styling explicit and avoid utility-only composition. Do not use inline CSS.

### Base Components (BEM)
**Buttons**
```html
<a class="btn btn--primary">Primary</a>
<a class="btn btn--secondary">Secondary</a>
<button class="btn btn--danger">Delete</button>
<button class="btn btn--success">Enable</button>
<button class="btn btn--sm btn--secondary">Small</button>
<button class="btn btn--icon" aria-label="Edit">...</button>
```

**Badges**
```html
<span class="badge badge--active">Active</span>
<span class="badge badge--inactive">Inactive</span>
<span class="badge badge--valid">Valid</span>
<span class="badge badge--invalid">Invalid</span>
<span class="badge badge--info">Info</span>
```

**Alerts**
```html
<div class="alert alert--success">Success message</div>
<div class="alert alert--error">Error message</div>
<div class="alert alert--warning">Warning message</div>
```

**Cards**
```html
<div class="card">
  <div class="card__header">
    <h2 class="card__title">Title</h2>
  </div>
  <div class="card__body">...</div>
</div>
```

**Tables**
```html
<div class="table-container">
  <table class="table">
    <thead>...</thead>
    <tbody>...</tbody>
  </table>
</div>
```
* Use `table__actions` for action button groups.
* Numeric columns use `table__cell--number`.
* Tickers and codes use `table__cell--mono`.

**Forms**
```html
<div class="form-group">
  <label class="form-label" for="name">Name</label>
  <input class="form-input" id="name">
  <p class="form-help">Help text</p>
  <p class="form-error">Validation error</p>
</div>
```
* Validation uses `form-input--error` and `form-error`.
* Use `form-row` and `form-row--3` for multi-column form layouts.
* Use `form-textarea--code` for JSON/code textareas.

**Filter Bar**
```html
<div class="filter-bar">
  <div class="filter-bar__tabs">
    <a class="filter-tab filter-tab--active">All</a>
  </div>
  <div class="filter-bar__controls">
    <select class="search-input">...</select>
    <input class="search-input" type="text">
  </div>
</div>
```

**Empty State**
```html
<div class="empty-state">
  <h3 class="empty-state__title">No results</h3>
  <p class="empty-state__text">...</p>
</div>
```

**Detail Grid / JSON**
```html
<div class="detail-grid">
  <div class="detail-label">Label</div>
  <div class="detail-value">Value</div>
</div>
<pre class="json-display">...</pre>
```

**Modal**
```html
<div class="modal">
  <div class="modal__backdrop"></div>
  <div class="modal__content">
    <div class="modal__header">...</div>
    <div class="modal__body">...</div>
    <div class="modal__footer">...</div>
  </div>
</div>
```

### Required BEM Extensions (Ensure Present in `admin.css`)
* `badge--info`, `badge--warning`, `badge--danger`, `badge--success`
* `text-muted`, `text-sm`, `text-mono`, `text-success`, `text-warning`, `text-danger`
* `table__cell--number`, `table__cell--mono`
* `filter-bar__controls`, `search-input--compact`, `table__sort`
* `form-row`, `form-row--3`, `form-textarea--code`, `card__subtitle`, `required`
* `card--spaced`, `detail-value--inline`, `modal--hidden`
* `metrics-grid`, `metrics-section`
* `loading-state`, `skeleton`
* Modal styles (`modal*`) currently live inline; move to `admin.css`

## 3. Required Deliverables (Views)
All view templates in scope must be redesigned to match this spec.

### A. Global Layout (`yii/src/views/layouts/main.php`)
**Data contract:** `$this`, `$content`
* **Header:** `admin-header` with `bg-surface`, border bottom, logo left, nav right.
* **Nav Links:** Peer Groups, Collection Policies, Collection Runs.
* **Footer:** `admin-footer` with `bg-brand-dark` and `text-inverse` at low opacity.

### B. Peer Groups (`yii/src/views/peer-group/*`)
**Index (`index.php`)**
* **Data contract:** `$groups`, `$counts`, `$sectors`, `$currentSector`, `$currentStatus`, `$currentSearch`, `$currentOrder`, `$currentDir`
* **Elements:** page header + primary CTA, filter bar tabs, sector select, search input, table with actions.
* **Status:** use `badge--active` / `badge--inactive` for group status.
* **Run status badges:** use `badge--valid` (complete + gate passed), `badge--invalid` (failed or gate failed), `badge--info` (running).

**View (`view.php`)**
* **Data contract:** `$group`, `$members`, `$runs`
* **Elements:**
  * Details card with status badge.
  * Members table with focal status badge and actions.
  * Collection section with alerts for inactive/no members.
  * Runs table with status and issues.
  * Add-members modal (move modal styles into `admin.css`).

**Form (`_form.php`)**
* **Data contract:** `$name`, `$slug`, `$sector`, `$description`, `$policyId`, `$policies`, `$errors`, `$isCreate`
* **Elements:** use `form-group`, `form-label`, `form-input`, `form-textarea`, `form-actions`.

**Create/Update (`create.php`, `update.php`)**
* Wrap `_form.php`, set page header and breadcrumb/toolbar actions as needed.

### C. Collection Policies (`yii/src/views/collection-policy/*`)
**Index (`index.php`)**
* **Data contract:** `$policies`
* **Elements:** page header + CTA, table with description meta and sector default badge.

**View (`view.php`)**
* **Data contract:** `$policy`
* **Elements:** details card, macro requirements, data requirements.
* **JSON sections:** use `json-display` for requirements/metrics.
* **Metrics layout:** use a grid class defined in `admin.css` (e.g., `metrics-grid` and `metrics-section` as BEM blocks).

**Form (`_form.php`)**
* **Data contract:** `$slug`, `$name`, `$description`, `$historyYears`, `$quartersToFetch`, `$valuationMetrics`, `$annualFinancialMetrics`, `$quarterlyFinancialMetrics`, `$operationalMetrics`, `$commodityBenchmark`, `$marginProxy`, `$sectorIndex`, `$requiredIndicators`, `$optionalIndicators`, `$errors`, `$isUpdate`
* **Elements:** standard form inputs, JSON textarea fields with `form-textarea`.

**Create/Update (`create.php`, `update.php`)**
* Wrap `_form.php`, add toolbar actions (Save/Cancel) using BEM buttons.

### D. Collection Runs (`yii/src/views/collection-run/*`)
**Index (`index.php`)** (new)
* **Data contract:** `$runs`, `$currentStatus`, `$currentSearch`, `$pagination`, `$totalCount`, `$statusOptions`
* **Elements:** table list with filters and status badges.

**View (`view.php`)**
* **Data contract:** `$run`, `$errors`, `$warnings`
* **Elements:**
  * Status badge in the details card header using run status.
  * Detail grid with industry/peer group, datapack ID, started/completed, duration, companies success/failed/total, output file.
  * Errors and warnings tables (no console log view).

## 4. Interaction & State Requirements
* **Empty states:** use `empty-state` block.
* **Loading states:** add `loading-state` and `skeleton` patterns in `admin.css` for async tables/forms.
* **Validation errors:** use `form-input--error` + `form-error`.
* **ARIA:** icon-only buttons require `aria-label`.
* **Pagination:** add a `pagination` block in `admin.css` and use on index tables where results exceed one page.
* **Pagination widget:** use Yii `LinkPager` to render numbered pages.
* **No inline styles:** move any inline styles into `admin.css` as BEM blocks/modifiers.

## 5. Dark Mode (Documentation Only)
* Tokens support dark mode via `[data-theme="dark"]` in `yii/web/css/tokens.css`.
* Do not implement a toggle in this redesign; document usage only.
* All new CSS must use tokens so dark mode works without overrides.

## 6. Technical Constraints
* Use Yii2 `Html` helper for encoding and links.
* No inline CSS.
* Do not introduce new naming conventions outside BEM.
* Ensure strict adherence to the institutional aesthetic (high information density, minimal ornament).

## 7. Version History
| Date | Version | Notes |
| --- | --- | --- |
| 2026-01-03 | v1.0 | Initial spec baseline. |
| 2026-01-03 | v1.1 | Add version history and align with BEM brand guide v1.3.1. |
