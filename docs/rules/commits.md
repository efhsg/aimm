# Commit Message Format

```
TYPE(scope): description

[optional body]

[optional footer]
```

## Types

| Type | When |
|------|------|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | Code change, no behavior change |
| `test` | Adding/updating tests |
| `docs` | Documentation only |
| `chore` | Maintenance (deps, config) |
| `wip` | Work in progress (squash before merge) |

## Examples

```
feat(collection): add Reuters adapter for valuation metrics
fix(gate): handle null peer average in gap calculation
refactor(handlers): extract rate limiting to dedicated skill
test(adapters): add Yahoo Finance fixture for Q4 2024
docs(skills): add validate-analysis-gate skill
```

## Rules

- Scope is optional but encouraged
- Description is imperative ("add" not "added")
- Body explains *why*, not *what*
- Footer references issues: `Closes #123`
- No `Co-Authored-By` or AI attribution in commits