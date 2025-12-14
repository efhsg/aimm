# MoneyMonkey Rules

Global guardrails for all development. These apply to every task, every prompt, every commit.

## Coding Conventions

### PHP

- **PSR-12** formatting
- **Explicit imports** — no aliases unless collision
- **No `declare(strict_types=1);`** — project convention
- **Type hints** on all parameters and return types
- **No business logic in controllers** — delegate to handlers
- **Services via DI** — `Yii::$container->get(ClassName::class)`

### Python

- **PEP 8** formatting
- **Type hints** on functions
- **No business logic in renderer** — receives DTO, outputs PDF

### General

- **No magic strings** — use constants or enums
- **No silent failures** — log or throw
- **No fabricated data** — document as not_found instead

## Folder Taxonomy

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

## Security Policies

### Scope Enforcement

- Handlers validate user has access before operating
- Never trust client-provided IDs without verification
- Log access attempts with user context

### Data Provenance

- Every datapoint must have source attribution
- Never fabricate financial data
- Document failed collection attempts

### Secrets

- No credentials in code
- Use environment variables or Yii params
- Never log sensitive values

## Testing Requirements

### Minimum Coverage

- **Unit tests** for: calculators, validators, transformers, factories
- **Integration tests** for: gates, handlers (happy path + key failures)
- **Fixture tests** for: adapters (real HTML snapshots)

### Test Naming

```php
public function testCalculatesGapWhenBothValuesPresent(): void
public function testReturnsNullWhenPeerAverageIsZero(): void
public function testFailsGateOnMissingRequiredMetric(): void
```

Pattern: `test{Action}{Condition}` or `test{Action}When{Scenario}`

### No Tests For

- Simple getters/setters
- Framework code
- Third-party libraries

## Commit Message Format

```
TYPE(scope): description

[optional body]

[optional footer]
```

### Types

| Type | When |
|------|------|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | Code change, no behavior change |
| `test` | Adding/updating tests |
| `docs` | Documentation only |
| `chore` | Maintenance (deps, config) |
| `wip` | Work in progress (squash before merge) |

### Examples

```
feat(collection): add Reuters adapter for valuation metrics
fix(gate): handle null peer average in gap calculation
refactor(handlers): extract rate limiting to dedicated skill
test(adapters): add Yahoo Finance fixture for Q4 2024
docs(skills): add validate-analysis-gate skill
```

### Rules

- Scope is optional but encouraged
- Description is imperative ("add" not "added")
- Body explains *why*, not *what*
- Footer references issues: `Closes #123`

## Code Review Checklist

Before approving:

- [ ] Follows folder taxonomy
- [ ] No catch-all folders created
- [ ] Type hints present
- [ ] Tests for new logic
- [ ] Provenance recorded for data operations
- [ ] No silent failures
- [ ] Commit messages follow format

## Agent Instructions

When working on this codebase:

1. **Read RULES.md first** — these apply to everything
2. **Check skills/index.md** — find relevant skills
3. **Load only needed skills** — minimize context
4. **Follow DoD** — skill is done when DoD passes
5. **Create skills for gaps** — if behavior isn't covered, write a skill
6. **Update index** — keep skill registry current
