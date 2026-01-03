# Admin UI

The Admin UI provides a web interface for managing pipeline inputs and monitoring collection runs.

## Access Control

### Authentication

- **Method:** HTTP Basic Auth via `AdminAuthFilter`
- **Credentials:** Environment variables `ADMIN_USERNAME` and `ADMIN_PASSWORD`

### Authorization

All authenticated users have full access. There are no role-based restrictions.

### Audit Trail

All create/update operations log:
- `created_by` / `updated_by` with username
- `created_at` / `updated_at` timestamps

## Entities

The Admin UI manages four core entities:

| Entity | Purpose |
|--------|---------|
| [Industry Configs](/admin-ui/industry-configs) | Industry-level inputs for collection |
| [Peer Groups](/admin-ui/peer-groups) | Focal company + peer sets |
| [Collection Policies](/admin-ui/collection-policies) | Data requirements + macro rules |
| [Collection Runs](/admin-ui/collection-runs) | Execution history + outcomes |

## How It Maps to the Pipeline

```
┌─────────────────────┐     ┌─────────────────────┐
│  Industry Config    │     │  Collection Policy  │
│  (industry inputs)  │     │  (requirements)     │
└─────────┬───────────┘     └─────────┬───────────┘
          │                           │
          │         ┌─────────────────┘
          │         │
          ▼         ▼
┌─────────────────────┐
│     Peer Group      │
│  (focal + peers)    │
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
│      Artifacts      │
│  datapack, DTO, PDF │
└─────────────────────┘
```

### Relationship Summary

1. **Industry Config** defines the industry inputs for collection (companies, macro requirements)
2. **Collection Policy** defines data requirements (which metrics, how much history)
3. **Peer Group** links a policy to a set of companies (focal + peers)
4. **Collection Run** executes the pipeline and produces artifacts

## Navigation

| Page | Purpose |
|------|---------|
| [Industry Configs](/admin-ui/industry-configs) | Manage industry configuration records |
| [Peer Groups](/admin-ui/peer-groups) | Manage peer sets and trigger collection |
| [Collection Policies](/admin-ui/collection-policies) | Define data collection requirements |
| [Collection Runs](/admin-ui/collection-runs) | View run history and diagnostics |

## Environment Setup

Set the following environment variables:

```bash
ADMIN_USERNAME=admin
ADMIN_PASSWORD=your-secure-password
```

::: warning Security
Use strong, unique passwords. Never commit credentials to version control.
:::
