# Coding Standards

## PHP

- **PSR-12** formatting
- **Explicit imports** — no aliases unless collision
- **`declare(strict_types=1);`** — required in all PHP files
- PHP 8.x with full type hints for parameters, return types, and properties
- **No business logic in controllers** — delegate to handlers (see architecture.md)

## Documentation

- Only add PHPDoc blocks when documenting `@throws` annotations (required) or when types cannot be expressed in signatures
- Function and method names should be self-explanatory
- Add inline comments only for non-obvious logic

## Python

- **PEP 8** formatting
- **Type hints** on functions
- **No business logic in renderer** — receives DTO, outputs PDF

## Design Principles

- **SOLID**
  - Single Responsibility: one reason to change per class
  - Open/Closed: extend via inheritance or composition, don't modify working code
  - Liskov Substitution: subtypes must be substitutable for their base types
  - Interface Segregation: prefer small, focused interfaces
  - Dependency Inversion: depend on abstractions, not concretions

- **DRY** (Don't Repeat Yourself): extract repeated logic into reusable methods or classes

- **YAGNI** (You Aren't Gonna Need It): only implement what's currently required, no speculative features

## General

- **No magic strings** — use constants or enums
- **No silent failures** — log or throw
- **Data integrity** — see security.md for provenance rules

## References

- **Architecture**: `.claude/rules/architecture.md`
- **Security**: `.claude/rules/security.md`
- **Tests**: `.claude/rules/testing.md`
- **Migrations**: `.claude/skills/create-migration.md`
