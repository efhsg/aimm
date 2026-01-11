# Frontend Design

Create production-grade frontend interfaces following AIMM's institutional finance design system. Use this skill when building or modifying UI components, views, or styles across admin UI, documentation site, or PDF reports.

## Overview

AIMM interfaces must feel institutional-grade: sober, data-dense, and reliable. All design work must comply with the existing design system. Do not introduce new colors, fonts, or patterns without updating the design tokens.

**Three interface contexts:**
- **Admin UI** — Yii2 PHP templates (`yii/src/views/`)
- **Documentation** — VitePress site (`site/.vitepress/theme/`)
- **PDF Reports** — HTML/CSS to PDF via Gotenberg (`yii/src/views/report/`)

## Design System Compliance (Required)

Before creating any UI:

1. **Use design tokens** from `yii/web/css/tokens.css`
2. **Follow BEM methodology** for class naming
3. **Reference existing patterns** in `docs/design/frontend/frontend_redesign.md`
4. **Check component library** in `yii/web/css/admin.css`

### Token Usage

```css
/* CORRECT - Use CSS variables */
.card {
  background: var(--bg-surface);
  border: 1px solid var(--border-default);
  color: var(--text-primary);
}

/* WRONG - Hardcoded values */
.card {
  background: #ffffff;
  border: 1px solid #d0dce0;
  color: #1a3540;
}
```

### BEM Class Naming

```html
<!-- Block -->
<div class="card">
  <!-- Element -->
  <div class="card__header">
    <h2 class="card__title">Title</h2>
  </div>
  <div class="card__body">...</div>
</div>

<!-- Block with Modifier -->
<button class="btn btn--primary">Primary</button>
<button class="btn btn--danger btn--sm">Small Delete</button>
```

## Color Palette

| Token | Value | Use For |
|-------|-------|---------|
| `--brand-primary` | `#1a4a55` | Headings, primary actions, links |
| `--brand-accent` | `#A68248` | Premium CTAs, emphasis, gold highlights |
| `--color-success` | `#3b755f` | Valid states, positive changes |
| `--color-error` | `#a5443f` | Errors, deletions, negative changes |
| `--color-warning` | `#b8860b` | Warnings, pending states |
| `--text-primary` | `#1a3540` | Body text (12.4:1 contrast) |
| `--text-secondary` | `#5a7a88` | Muted text, labels (4.7:1 contrast) |
| `--bg-surface` | `#ffffff` | Cards, elevated surfaces |
| `--bg-page` | `#f0f4f5` | Page background |

## Typography

| Family | Token | Use For |
|--------|-------|---------|
| Inter | `--font-sans` | All body text, headings, UI labels |
| IBM Plex Mono | `--font-mono` | Tickers, numbers, code, JSON |

**Do not substitute fonts.** The design system uses Inter for readability and IBM Plex Mono for financial data precision.

```css
/* Numeric/financial data */
.table__cell--number {
  font-family: var(--font-mono);
  font-feature-settings: 'tnum' 1; /* Tabular numbers */
  text-align: right;
}
```

## Context: Admin UI (Yii2)

### Component Patterns

**Buttons:**
```html
<a class="btn btn--primary">Primary Action</a>
<button class="btn btn--secondary">Secondary</button>
<button class="btn btn--danger">Delete</button>
<button class="btn btn--sm btn--secondary">Small</button>
```

**Badges:**
```html
<span class="badge badge--active">Active</span>
<span class="badge badge--inactive">Inactive</span>
<span class="badge badge--valid">Valid</span>
<span class="badge badge--invalid">Failed</span>
```

**Tables:**
```html
<div class="table-container">
  <table class="table">
    <thead>...</thead>
    <tbody>
      <tr>
        <td class="table__cell--mono">AAPL</td>
        <td class="table__cell--number">185.92</td>
        <td class="table__actions">
          <a class="btn btn--sm btn--secondary">View</a>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

**Forms:**
```html
<div class="form-group">
  <label class="form-label" for="name">Name</label>
  <input class="form-input" id="name" type="text">
  <p class="form-error">Validation error message</p>
</div>
```

### Layout Principles

- Use `admin-container` for main content width
- Cards group related content with `card`, `card__header`, `card__body`
- Filter bars use `filter-bar` with `filter-bar__tabs` and `filter-bar__controls`
- Empty states use `empty-state` block

### Yii2 View Conventions

- Use `Html::encode()` for all user-provided values
- Partials prefixed with underscore: `_form.php`, `_header.php`
- No inline styles — all CSS goes in `admin.css`
- No JavaScript frameworks — vanilla JS only

## Context: Documentation (VitePress)

### Theme Files

- Layout: `site/.vitepress/theme/Layout.vue`
- Styles: `site/.vitepress/theme/custom.css`
- Config: `site/.vitepress/config.ts`

### VitePress Token Mapping

```css
:root {
  --vp-c-brand-1: #1a4a55;    /* Maps to --brand-primary */
  --vp-c-brand-2: #153d47;
  --vp-c-brand-3: #0d2a32;
  --vp-c-tip-1: #1a4a55;
  --vp-c-warning-1: #b8860b;
  --vp-c-danger-1: #a5443f;
}
```

### Guidelines

- Extend the custom theme, do not replace it
- New components go in `custom.css` using AIMM tokens
- Maintain consistent branding with admin UI
- Test both light and dark modes

## Context: PDF Reports

### Print CSS Requirements

PDF reports are rendered by Gotenberg (Chromium) from HTML/CSS.

**Page Setup:**
```css
@page {
  size: A4;               /* 210mm × 297mm */
  margin: 25mm 15mm 20mm 15mm;
}

@page :first {
  margin-top: 15mm;       /* Less top margin on cover */
}
```

**Content Flow:**
```css
/* Prevent breaking inside elements */
.metric-row,
.company-card {
  break-inside: avoid;
}

/* Keep headers with content */
h2, h3 {
  break-after: avoid;
}

/* Force page breaks */
.page-break {
  break-before: page;
}
```

**Color Preservation:**
```css
* {
  print-color-adjust: exact;
  -webkit-print-color-adjust: exact;
}
```

**Table Headers:**
```css
thead {
  display: table-header-group; /* Repeat on each page */
}
```

### PDF Constraints

1. **No external resources** — All fonts, images, CSS must be bundled
2. **WOFF2 fonts** — Use bundled Inter and IBM Plex Mono from `yii/web/fonts/`
3. **Base64 images** — Or include in bundle; no HTTP URLs
4. **No JavaScript** — Static HTML only

### File Locations

- Templates: `yii/src/views/report/`
- Layout: `yii/src/views/report/layouts/pdf_main.php`
- Partials: `yii/src/views/report/partials/`
- Styles: `yii/web/scss/report.scss` → `yii/web/css/report.css`

## Aesthetics

### Do

- Professional institutional finance appearance
- High information density with clear hierarchy
- Structured grids for financial data
- Generous whitespace around content blocks
- Consistent spacing using `--space-*` tokens
- Subtle shadows (`--shadow-sm`, `--shadow-md`) for elevation

### Don't

- Decorative elements without purpose
- Asymmetric or experimental layouts
- Playful, casual, or trendy aesthetics
- Bold color blocks as backgrounds
- Animations beyond subtle transitions
- Custom fonts or colors outside the system

## Accessibility

- **Touch targets:** Minimum 44px (`--touch-target-min`)
- **Focus states:** Visible focus ring using `--state-focus`
- **Contrast:** WCAG AA minimum (4.5:1 for text)
- **ARIA labels:** Required for icon-only buttons
- **Reduced motion:** Respect `prefers-reduced-motion`

## Anti-Patterns to Avoid

| Anti-Pattern | Correct Approach |
|--------------|------------------|
| Hardcoded hex colors | Use `var(--color-*)` tokens |
| Generic class names (`box`, `container`) | Use BEM naming |
| Inline styles | Add to `admin.css` or `report.scss` |
| External fonts in PDFs | Bundle WOFF2 from `yii/web/fonts/` |
| Complex JavaScript in admin | CSS-first, vanilla JS backup |
| New design patterns | Extend existing components |

## Verification Checklist

Before completing frontend work:

- [ ] All colors use design tokens
- [ ] Classes follow BEM methodology
- [ ] Matches existing component patterns
- [ ] Works in dark mode (if applicable)
- [ ] Accessibility requirements met
- [ ] PDF renders correctly (if report styling)
- [ ] No inline styles added
