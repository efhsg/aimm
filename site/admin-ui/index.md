# Admin UI

The Admin UI provides a web interface for managing industries, collection policies,
and collection runs.

## Access Control

### Authentication

- **Method:** HTTP Basic Auth via `AdminAuthFilter`
- **Credentials:** Environment variables `ADMIN_USERNAME` and `ADMIN_PASSWORD`

### Authorization

All authenticated users have full access. There are no role-based restrictions.

### Audit Trail

Create/update operations store:
- `created_by` / `updated_by` with username
- `created_at` / `updated_at` timestamps

## Entities

The Admin UI manages these core entities:

| Entity | Purpose |
|--------|---------|
| Industries | Industry metadata and company membership |
| Collection Policies | Data requirements + macro rules |
| Collection Runs | Execution history + outcomes |

## How It Maps to the Pipeline

```
┌─────────────────────┐
│  Collection Policy  │
│  (requirements)     │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│     Industry        │
│  (companies + meta) │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│   Collection Run    │
│  (execution)        │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│   Dossier + Reports │
│  (DB + PDFs)        │
└─────────────────────┘
```

### Relationship Summary

1. **Collection Policy** defines what data to collect (metrics, history, macro inputs).
2. **Industry** links a policy to a set of companies.
3. **Collection Run** executes Phase 1 for an industry and records results.

## Navigation

| Page | Purpose |
|------|---------|
| Industries | Manage industries and company membership |
| Collection Policies | Define data collection requirements |
| Collection Runs | View run history and diagnostics |

## Environment Setup

Set the following environment variables:

```bash
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your-secure-password
```

::: warning Security
Use strong, unique passwords. Never commit credentials to version control.
:::
