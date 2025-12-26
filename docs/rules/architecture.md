# Architecture & Folder Taxonomy

Use specific folders, not catch-alls:

| Folder | Purpose | Anti-pattern |
|--------|---------|--------------|
| `handlers/` | Business flow, orchestration | ~~services/~~ |
| `queries/` | Data retrieval, no business rules | — |
| `validators/` | Validation logic | — |
| `transformers/` | Data shape conversion | ~~helpers/~~ |
| `factories/` | Object construction | ~~builders/~~ |
| `dto/` | Typed data transfer objects | ~~arrays~~ |
| `clients/` | External integrations | — |
| `adapters/` | External → internal mapping | — |
| `enums/` | Enumerated types | — |
| `exceptions/` | Custom exceptions | — |

**Banned folders:** `services/`, `helpers/`, `components/` (except Yii framework), `utils/`, `misc/`
