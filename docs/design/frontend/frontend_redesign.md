# Frontend Redesign Specification: AIMM

## Role & Objective
You are a **Senior Frontend Engineer & UI/UX Designer**. Your goal is to redesign the **AIMM (Equity Intelligence Pipeline)** web application using the strictly defined **AIMM Master Brand & UI System**.

**Aesthetic:** Institutional Grade, Goldman Sachs-level equity research tool. Precise, data-heavy, reliable, high-contrast. No "startup" fluff.

## 1. Design System Tokens (Strict Compliance)
You must utilize the following CSS variables defined in the project's CSS:

### Colors
*   **Primary:** `var(--brand-primary)` (`#1a4a55`) - Headings, Primary Actions
*   **Dark:** `var(--brand-dark)` (`#0d2a32`) - Sidebar, Footer
*   **Accent:** `var(--brand-accent)` (`#A68248`) - Premium CTAs
*   **Backgrounds:**
    *   Page: `var(--bg-page)` (`#f0f4f5`)
    *   Surface: `var(--bg-surface)` (`#ffffff`)
    *   Elevated: `var(--bg-elevated)` (`#f8fafa`)
*   **Semantic:**
    *   Success: `var(--color-success)` (`#3b755f`)
    *   Error: `var(--color-error)` (`#a5443f`)
    *   Warning: `var(--color-warning)` (`#b8860b`)
*   **Text:**
    *   Primary: `var(--text-primary)` (`#1a3540`)
    *   Secondary: `var(--text-secondary)` (`#5a7a88`)
    *   Mono: `var(--font-mono)` for all financial data/tickers.

### Layout & Spacing
*   **Container:** `container` class (max-width `var(--container-xl)`).
*   **Grid:** 12-column grid system.
*   **Spacing:** Use `var(--space-1)` through `var(--space-12)`.

## 2. Component Implementation Patterns

### Buttons
```html
<button class="btn btn-primary">Action</button>
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-outline">View</button>
```

### Badges
```html
<span class="badge badge-success">Validated</span>
<span class="badge badge-warning">Pending</span>
<span class="badge badge-error">Flagged</span>
```

### Data Tables (Critical)
*   Use `data-table` class.
*   Headers: `text-xs`, `text-tertiary`, `uppercase`.
*   Data: `font-mono` for numbers/tickers.
*   Right-align all numerical columns.

### Cards
*   `bg-surface`, `border-default`, `radius-lg`, `shadow-sm`.

## 3. Required Deliverables

Generate the PHP/HTML code for the following Yii2 views. Assume `$this` is the View object.

### A. Global Layout (`views/layouts/main.php`)
*   **Structure:** Fixed Header + Main Content + Footer.
*   **Header:** `bg-surface`, border bottom. Logo left, Nav links right.
*   **Nav Links:** Peer Groups, Collection Policies, Collection Runs, Industry Config.
*   **Footer:** `brand-dark`, `text-white` (low opacity). "AIMM — Equity Intelligence Pipeline".

### B. Industry Config Index (`views/industry-config/index.php`)
*   **Context:** List of configured industries.
*   **Elements:**
    *   Page Header: Title + "Create New" (Primary Button).
    *   Filter Bar (Complex Component): Search input + Active/Inactive select.
    *   Data Table: ID, Name, Created By, Last Updated, Status (Badge), Actions (Icon buttons).

### C. Industry Config View (`views/industry-config/view.php`)
*   **Context:** Detail view of a single industry.
*   **Elements:**
    *   Header: Breadcrumbs + Title + Actions (Edit, Toggle Active).
    *   2-Column Grid:
        *   **Left (Meta):** Card showing UUID, timestamps, status.
        *   **Right (Config):** Code block showing the `config_json` (use `font-mono`, `bg-muted`, preserve whitespace).

### D. Collection Run View (`views/collection-run/view.php`)
*   **Context:** Monitoring a specific data collection job.
*   **Elements:**
    *   Status Banner: Large colored banner based on status (Success/Fail/Running).
    *   Stats Grid: 4 cards (Duration, Items Processed, Errors, Memory Usage).
    *   Logs: A console-like window (`bg-brand-dark`, `text-mono`, `text-xs`) showing log entries.

## 4. Technical Constraints
*   Use Yii2 `Html` helper for encoding and links.
*   **Do not** write inline CSS. Use the provided CSS variable classes.
*   Ensure strict adherence to the **Goldman Sachs/Institutional** aesthetic (sober, clean, high information density).
