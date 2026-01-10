# Industries

Industries define the company set and metadata for collection and analysis.

## Index View

### Features

- Filter by status (active/inactive)
- Filter by sector
- Search by name or slug
- Sort columns

### Columns

| Column | Description |
|--------|-------------|
| Name | Industry display name |
| Sector | Sector name |
| Companies | Company count |
| Policy | Assigned collection policy (if any) |
| Status | Active/Inactive |
| Last Run | Most recent collection run status |
| Actions | View, Edit, Enable/Disable |

## Detail View

### Industry Metadata

| Field | Description |
|-------|-------------|
| Slug | URL-safe identifier used in CLI |
| Sector | Sector name |
| Policy | Linked collection policy (optional) |
| Description | Optional text |
| Created | Timestamp and user |
| Updated | Timestamp and user |

### Companies

- List of member companies (ticker + name)
- Add companies by ticker
- Remove companies from the industry

### Collection & Analysis

- **Collect Data**: triggers Phase 1 for the industry
- **Analyze All**: runs ranking analysis for all analyzable companies
- **View Rankings**: shows latest analysis report

## Create/Update

### Fields

| Field | Description |
|-------|-------------|
| Name | Industry display name |
| Slug | URL-safe identifier (auto-generated on create) |
| Sector | Required sector selection |
| Description | Optional notes |
| Collection Policy | Optional policy assignment |

### Validation Rules

- Slug must be lowercase letters, numbers, and hyphens
- Sector is required on create
