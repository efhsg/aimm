# Design: Web Data Collection Interface

**Status:** Draft
**Last Updated:** 2026-01-01
**Author:** Claude Code

---

## 1. Overview

### 1.1 Objective

Provide a user-friendly web interface for Data Analysts and Operators to manage industry peer groups, configure collection policies, and trigger data collection pipelines. This replaces the manual CLI workflow defined in `docs/user/data-collection-workflow.md`.

### 1.2 Background

Currently, users must execute CLI commands via Docker to:
- Create and manage peer groups
- Add/remove companies from groups
- Configure collection policies
- Trigger data collection runs

This creates friction for non-technical analysts and increases operational overhead.

---

## 2. Command Analysis

### 2.1 Peer Group Management (`PeerGroupController`)

| CLI Command | Parameters | Web Equivalent |
|-------------|------------|----------------|
| `peer-group/create` | name, --slug, --sector, --policy, --description | Create form with fields |
| `peer-group/add` | groupSlug, tickers[] | Bulk ticker input on detail page |
| `peer-group/remove` | groupSlug, ticker | Remove button per member row |
| `peer-group/set-focal` | groupSlug, ticker | "Set Focal" action per member |
| `peer-group/list` | --sector | Index page with sector filter |
| `peer-group/show` | groupSlug | Detail/view page |
| `peer-group/activate` | groupSlug | Toggle button |
| `peer-group/deactivate` | groupSlug | Toggle button |
| `peer-group/assign-policy` | groupSlug, policyId | Dropdown selector |

### 2.2 Collection Policy Management (`CollectionPolicyController`)

| CLI Command | Parameters | Web Equivalent |
|-------------|------------|----------------|
| `collection-policy/create` | slug, jsonFile, --name, etc. | Create form with JSON editor |
| `collection-policy/list` | - | Index page |
| `collection-policy/show` | slug | Detail/view page |
| `collection-policy/set-default` | slug, sector | Action button |
| `collection-policy/clear-default` | sector | Action button |
| `collection-policy/export` | slug | Download JSON button |
| `collection-policy/delete` | slug | Delete with confirmation |

### 2.3 Data Collection (`CollectController`)

| CLI Command | Parameters | Web Equivalent |
|-------------|------------|----------------|
| `collect/industry` | id, --focal | "Start Collection" button + modal |

### 2.4 Key Observations

1. **Peer Group flow** is primary use case - analysts configure groups then collect
2. **Policy management** is secondary - typically done once per sector
3. **Collection trigger** needs status feedback - runs take 1-10 minutes
4. **Validation** is critical - tickers must resolve, slugs must be unique
5. **Existing patterns** - `IndustryConfigController` provides CRUD template

---

## 3. User Stories

### 3.1 Analyst Stories

- **US-01**: As an Analyst, I want to create a new Peer Group by defining its name, sector, and target tickers without using the CLI.
- **US-02**: As an Analyst, I want to add companies to an existing peer group using ticker symbols.
- **US-03**: As an Analyst, I want to set a focal company for comparative analysis.
- **US-04**: As an Analyst, I want to trigger a data collection run and see its progress/status.
- **US-05**: As an Analyst, I want to view the collection history for a peer group.

### 3.2 Operator Stories

- **US-06**: As an Operator, I want to monitor all active collection runs across the system.
- **US-07**: As an Operator, I want to create and manage collection policies.
- **US-08**: As an Operator, I want to view collection errors and warnings.

---

## 4. Functional Requirements

### 4.1 Peer Group Management

#### 4.1.1 List View (`/peer-group`)
- Table with columns: Name, Sector, Members, Focal, Policy, Status, Last Run, Actions
- Filter tabs: All | Active | Inactive
- Sector dropdown filter
- Search by name/slug
- Sortable columns
- "New Peer Group" button

#### 4.1.2 Create View (`/peer-group/create`)
- Form fields:
  - Name (required, text)
  - Slug (auto-generated from name, editable)
  - Sector (required, dropdown)
  - Description (optional, textarea)
  - Policy (optional, dropdown of available policies)
  - Initial Tickers (optional, textarea for bulk input)
- Validation: Slug uniqueness check via AJAX

#### 4.1.3 Detail View (`/peer-group/view/{slug}`)
- Header: Name, Sector, Status badge, Policy name
- Stats: Member count, Last collection status, Last collection time
- **Members Section:**
  - Table: Ticker, Company Name, Focal badge, Added date, Actions (Remove, Set Focal)
  - "Add Companies" button -> modal with ticker textarea
- **Collection Section:**
  - "Start Collection" button (disabled if inactive or running)
  - Recent runs table: Start time, Duration, Status, Errors/Warnings, View link
- **Settings Section:**
  - Edit metadata button
  - Change policy dropdown
  - Activate/Deactivate toggle

#### 4.1.4 Edit View (`/peer-group/update/{slug}`)
- Same fields as create (except slug is read-only)
- Member management is on detail view, not edit

### 4.2 Collection Policy Management

#### 4.2.1 List View (`/collection-policy`)
- Table: Name, Slug, History Years, Quarters, Default Sector, Actions
- "New Policy" button
- Search by name

#### 4.2.2 Create View (`/collection-policy/create`)
- Form fields:
  - Slug (required)
  - Name (required)
  - Description (optional)
  - History Years (default: 5)
  - Quarters to Fetch (default: 8)
  - Valuation Metrics (JSON array editor)
  - Annual/Quarterly/Operational Metrics (JSON editors)
  - Macro Settings (commodity benchmark, margin proxy, sector index)
  - Required/Optional Indicators (JSON arrays)
- JSON validation feedback (like IndustryConfig)

#### 4.2.3 Detail View (`/collection-policy/view/{slug}`)
- Display all policy settings in readable format
- "Export JSON" button
- "Set as Default for Sector" action
- Edit button

### 4.3 Collection Monitoring

#### 4.3.1 Run Status Polling
- When collection starts, show "Running..." status
- Poll `/collection-run/{runId}/status` every 5 seconds
- Update progress bar and status text
- On completion, refresh run history table

#### 4.3.2 Run Detail View (`/collection-run/view/{id}`)
- Summary: Industry, Datapack ID, Duration, Status
- Company breakdown: Success/Failed counts
- Error list: Severity, Code, Message, Ticker
- Warning list

---

## 5. Technical Design

### 5.1 Backend Components

#### 5.1.1 New Controllers

```
yii/src/controllers/
  PeerGroupController.php        # Web CRUD for peer groups
  CollectionPolicyController.php # Web CRUD for policies
  CollectionRunController.php    # Run status and history
```

#### 5.1.2 New Handlers

```
yii/src/handlers/peergroup/
  CreatePeerGroupInterface.php
  CreatePeerGroupHandler.php
  UpdatePeerGroupInterface.php
  UpdatePeerGroupHandler.php
  AddMembersInterface.php
  AddMembersHandler.php
  RemoveMemberInterface.php
  RemoveMemberHandler.php
  SetFocalInterface.php
  SetFocalHandler.php
  TogglePeerGroupInterface.php
  TogglePeerGroupHandler.php
  TriggerCollectionInterface.php
  TriggerCollectionHandler.php
  CollectPeerGroupInterface.php
  CollectPeerGroupHandler.php    # Resolves industry/focal, delegates to CollectIndustryInterface
```

**Note:** `TriggerCollectionHandler` depends on `CollectPeerGroupInterface`, not `CollectIndustryInterface` directly. The `CollectPeerGroupHandler` accepts PeerGroup context, resolves industry and focal company internally, and delegates to the existing `CollectIndustryInterface`. Controllers and UI code must not reference `CollectIndustryInterface`.

#### 5.1.3 New DTOs

```
yii/src/dto/peergroup/
  CreatePeerGroupRequest.php
  CreatePeerGroupResult.php
  UpdatePeerGroupRequest.php
  PeerGroupResponse.php          # For list/view responses
  PeerGroupMemberResponse.php
  AddMembersRequest.php
  AddMembersResult.php
  TriggerCollectionRequest.php
  TriggerCollectionResult.php
```

#### 5.1.4 New/Extended Queries

```
yii/src/queries/
  PeerGroupListQuery.php         # List with stats, filters, sorting
  CollectionRunQuery.php         # Run history by group
```

### 5.2 Database Schema

No schema changes required. Uses existing tables:
- `industry_peer_group`
- `industry_peer_group_member`
- `collection_policy`
- `collection_run`
- `collection_error`
- `company`

**Assumed Schema Support:**

This design assumes the existing schema provides:
- **Run status enum** — `collection_run.status` supports values for pending, running, completed, partial, and failed states
- **Timestamps** — `collection_run.started_at` and `collection_run.finished_at` columns exist for duration calculation
- **Error/warning linkage** — `collection_error` table is linked to `collection_run` via foreign key and includes severity level to distinguish errors from warnings

### 5.3 Views

```
yii/src/views/peer-group/
  index.php                      # List view
  view.php                       # Detail view with members
  create.php                     # Create form
  update.php                     # Edit form
  _form.php                      # Shared form partial
  _member_row.php                # Member table row partial
  _run_row.php                   # Run history row partial
  _add_members_modal.php         # Modal for bulk add

yii/src/views/collection-policy/
  index.php
  view.php
  create.php
  update.php
  _form.php
  _json_editor.php               # Reusable JSON editor (from industry-config)

yii/src/views/collection-run/
  view.php                       # Run detail with errors
```

### 5.4 CSS

**Token Files:**
- Import: `docs/design/frontend/style/tokens.css` (CSS custom properties)
- Reference: `docs/design/frontend/style/aimm-brand-guide-v1.3.html` (visual guide)
- Alternatives: `tokens.json` (JS/tooling), `_tokens.scss` (Sass)

**Required Tokens:**
| Category | Tokens |
|----------|--------|
| Colors | `--brand-primary`, `--color-success`, `--color-error`, `--color-warning` |
| Typography | `--font-sans` (Inter), `--font-mono` (IBM Plex Mono), `--text-sm`, `--text-base` |
| Spacing | `--space-*` scale (4px base unit) |
| Borders | `--border-default`, `--radius-md`, `--radius-lg` |
| Shadows | `--shadow-sm`, `--shadow-md`, `--shadow-lg` |
| States | `--state-hover`, `--state-focus`, `--state-disabled-*` |
| Z-index | `--z-modal`, `--z-overlay` (for modals) |
| Accessibility | `--touch-target-min` (44px), `--focus-ring-width` |

**Extend `admin.css` with:**
- Modal styles (use `--shadow-lg`, `--radius-lg`, `--z-modal`)
- Progress bar styles (use `--viz-*` colors)
- Member management styles
- Status badges (reuse `.badge-success`, `.badge-error`, `.badge-warning` from brand guide)

### 5.5 JavaScript

```
yii/web/js/
  peer-group.js                  # Status polling, modal handling
  json-editor.js                 # Reuse from industry-config
```

### 5.6 Routes

```php
// config/web.php rules
'peer-group' => 'peer-group/index',
'peer-group/create' => 'peer-group/create',
'peer-group/<slug>' => 'peer-group/view',
'peer-group/<slug>/edit' => 'peer-group/update',
'peer-group/<slug>/toggle' => 'peer-group/toggle',
'peer-group/<slug>/add-members' => 'peer-group/add-members',
'peer-group/<slug>/remove-member' => 'peer-group/remove-member',
'peer-group/<slug>/set-focal' => 'peer-group/set-focal',
'peer-group/<slug>/collect' => 'peer-group/collect',

'collection-policy' => 'collection-policy/index',
'collection-policy/create' => 'collection-policy/create',
'collection-policy/<slug>' => 'collection-policy/view',
'collection-policy/<slug>/edit' => 'collection-policy/update',
'collection-policy/<slug>/delete' => 'collection-policy/delete',
'collection-policy/<slug>/export' => 'collection-policy/export',
'collection-policy/<slug>/set-default' => 'collection-policy/set-default',

'collection-run/<id>' => 'collection-run/view',
'collection-run/<id>/status' => 'collection-run/status',
```

---

## 6. UI/UX Design

### 6.1 Navigation

Update `layouts/main.php` to add nav links:
```html
<nav class="admin-header__nav">
    <a href="/industry-config">Industry Configs</a>
    <a href="/peer-group">Peer Groups</a>
    <a href="/collection-policy">Collection Policies</a>
</nav>
```

### 6.2 Peer Group List Wireframe

```
+------------------------------------------------------------------+
| Peer Groups                                    [+ New Peer Group] |
+------------------------------------------------------------------+
| [All (12)] [Active (10)] [Inactive (2)]    Sector: [All v] [___] |
+------------------------------------------------------------------+
| Name              | Sector | Members | Focal | Policy    | Status|
|-------------------|--------|---------|-------|-----------|-------|
| Global Supermajors| Energy |      5  | SHEL  | standard  | Active|
| US Tech Leaders   | Tech   |      8  | AAPL  | -         |Inactive|
+------------------------------------------------------------------+
```

### 6.3 Peer Group Detail Wireframe

```
+------------------------------------------------------------------+
| Global Energy Supermajors                      [Edit] [Deactivate]|
| Sector: Energy | Policy: standard-equity | Status: Active         |
+------------------------------------------------------------------+
| Members (5)                                    [+ Add Companies]  |
+------------------------------------------------------------------+
| Ticker | Company Name       | Focal | Added      | Actions       |
|--------|-------------------|-------|------------|---------------|
| SHEL   | Shell PLC         | [*]   | 2026-01-01 | [Set Focal][X]|
| XOM    | Exxon Mobil       |       | 2026-01-01 | [Set Focal][X]|
| BP     | BP PLC            |       | 2026-01-01 | [Set Focal][X]|
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| Collection                                    [Start Collection]  |
+------------------------------------------------------------------+
| Recent Runs                                                       |
+------------------------------------------------------------------+
| Started          | Duration | Status   | Errors | Warnings | View|
|------------------|----------|----------|--------|----------|-----|
| 2026-01-01 10:00 | 45s      | Complete |    0   |     2    | [>] |
| 2025-12-31 14:30 | 52s      | Partial  |    1   |     3    | [>] |
+------------------------------------------------------------------+
```

### 6.4 Add Companies Modal

```
+----------------------------------------+
| Add Companies                     [X]  |
+----------------------------------------+
| Enter ticker symbols (one per line     |
| or comma-separated):                   |
|                                        |
| +----------------------------------+   |
| | SHEL                             |   |
| | XOM                              |   |
| | BP, CVX, TTE                     |   |
| +----------------------------------+   |
|                                        |
| [Cancel]                       [Add]   |
+----------------------------------------+
```

---

## 7. Security

**Scope:** This web interface is intended for internal/admin use only. It must not be exposed to the public internet without additional authentication layers (e.g., VPN, IP allowlist, or SSO integration).

### 7.1 Authentication

- Reuse `AdminAuthFilter` from IndustryConfigController
- HTTP Basic Auth with env vars: `ADMIN_USERNAME`, `ADMIN_PASSWORD`

### 7.2 Authorization

- All routes require admin/operator role (via auth filter)
- No public endpoints

### 7.3 Input Validation

- **Tickers**: Uppercase, alphanumeric + period (e.g., `SHELL.AS`)
- **Slugs**: Lowercase, alphanumeric + hyphen, unique
- **Sectors**: Validated against known list
- **Policy JSON**: Schema validation (reuse existing validator)

### 7.4 CSRF Protection

- All POST/DELETE actions require CSRF token
- Use Yii's built-in CSRF handling

### 7.5 Rate Limiting

- Collection trigger limited to 1 concurrent run per group
- Existing collection rate limiting preserved

---

## 8. Implementation Phases

### Phase 1: Peer Group CRUD (Core)

**Scope:**
- `PeerGroupController` with index/view/create/update/toggle actions
- `PeerGroupListQuery` for filtered, sorted list
- Basic handlers: CreatePeerGroup, UpdatePeerGroup, TogglePeerGroup
- Views: index, view, create, update, _form
- Navigation update

**Files:**
- `yii/src/controllers/PeerGroupController.php`
- `yii/src/queries/PeerGroupListQuery.php`
- `yii/src/handlers/peergroup/CreatePeerGroupHandler.php`
- `yii/src/handlers/peergroup/UpdatePeerGroupHandler.php`
- `yii/src/handlers/peergroup/TogglePeerGroupHandler.php`
- `yii/src/dto/peergroup/CreatePeerGroupRequest.php`
- `yii/src/dto/peergroup/PeerGroupResponse.php`
- `yii/src/views/peer-group/*.php`

**Tests:**
- Unit tests for handlers
- Controller action tests

### Phase 2: Member Management

**Scope:**
- Add Members action with bulk ticker parsing
- Remove Member action
- Set Focal action
- Modal UI for adding members
- Member table on detail view

**Files:**
- `yii/src/handlers/peergroup/AddMembersHandler.php`
- `yii/src/handlers/peergroup/RemoveMemberHandler.php`
- `yii/src/handlers/peergroup/SetFocalHandler.php`
- `yii/src/dto/peergroup/AddMembersRequest.php`
- `yii/src/views/peer-group/_add_members_modal.php`
- `yii/src/views/peer-group/_member_row.php`
- `yii/web/js/peer-group.js`

**Tests:**
- Handler tests for ticker validation edge cases
- Integration tests for member operations

### Phase 3: Collection Integration

**Scope:**
- Trigger collection from web UI
- Run status polling endpoint
- Collection history on detail view
- Link to existing `CollectIndustryInterface`

**Files:**
- `yii/src/handlers/peergroup/TriggerCollectionHandler.php`
- `yii/src/queries/CollectionRunListQuery.php`
- `yii/src/controllers/CollectionRunController.php`
- `yii/src/views/peer-group/_run_row.php`
- `yii/src/views/collection-run/view.php`

**Tests:**
- Integration test for collection trigger
- Status polling tests

### Phase 4: Collection Policy Web UI

**Scope:**
- CRUD for collection policies via web
- JSON editor for metric configuration
- Set/clear default for sector
- Export to JSON file

**Files:**
- `yii/src/controllers/CollectionPolicyController.php`
- `yii/src/handlers/collectionpolicy/*.php`
- `yii/src/views/collection-policy/*.php`

**Tests:**
- Handler tests for policy CRUD
- JSON validation tests

---

## 9. Testing Strategy

### 9.1 Unit Tests

| Component | Scenarios |
|-----------|-----------|
| CreatePeerGroupHandler | Valid input, duplicate slug, invalid sector |
| AddMembersHandler | Single ticker, bulk add, invalid tickers, duplicates |
| SetFocalHandler | Valid focal, non-member ticker |
| TriggerCollectionHandler | Group not found, already running, success |
| PeerGroupListQuery | Filter by sector, search, sort, pagination |

### 9.2 Integration Tests

| Flow | Test |
|------|------|
| Create group flow | Create -> Add members -> Set focal -> Verify |
| Collection flow | Trigger collection -> Poll status -> Verify completion |
| Policy assignment | Create policy -> Assign to group -> Verify resolution |

### 9.3 Manual Testing

- [ ] Create peer group with all fields
- [ ] Add 5+ tickers in bulk
- [ ] Remove a member
- [ ] Set focal company
- [ ] Trigger collection and verify status updates
- [ ] View collection run errors
- [ ] Filter/sort/search peer groups
- [ ] Create/edit collection policy
- [ ] Set policy as sector default

---

## 10. Open Questions

1. **Background jobs**: Should collection run synchronously or via queue?
   - *Recommendation*: Web UI should always enqueue collection runs asynchronously. Synchronous execution is limited to job dispatch only. This avoids HTTP timeouts, partial execution, and poor UX. Queue implementation may be deferred, but the `CollectPeerGroupInterface` must assume async execution. CLI behavior remains unchanged.

2. **Company auto-creation**: When adding unknown tickers, auto-create company records?
   - *Recommendation*: Yes, reuse `CompanyQuery::findOrCreate()` from CLI

3. **Concurrent collections**: Allow multiple simultaneous runs per group?
   - *Recommendation*: No, one active run per group to prevent data conflicts

4. **Sector list source**: Hardcode sectors or derive from existing data?
   - *Recommendation*: Derive from `collection_policy.is_default_for_sector` + config list

---

## 11. Dependencies

- Existing `CollectIndustryInterface` and handler
- Existing `PeerGroupQuery` and `PeerGroupMemberQuery`
- Existing `CollectionPolicyQuery`
- Existing `AdminAuthFilter`
- Design tokens: `docs/design/frontend/style/tokens.css`
- Brand guide: `docs/design/frontend/style/aimm-brand-guide-v1.3.html`
- Existing `admin.css` (must import and use design tokens)

---

## 12. Success Criteria

1. Analysts can create and manage peer groups without CLI access
2. Collection can be triggered and monitored via web UI
3. All existing CLI functionality is preserved (CLI remains available)
4. Response times under 200ms for CRUD operations
5. Collection status updates visible within 5 seconds
