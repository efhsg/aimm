# Coding Standards

## PHP

- **PSR-12** formatting
- **Explicit imports** — no aliases unless collision
- **`declare(strict_types=1);`** — required in all PHP files
- **Type hints** on all parameters and return types
- **No business logic in controllers** — delegate to handlers
- **Services via DI** — `Yii::$container->get(ClassName::class)`

## Python

- **PEP 8** formatting
- **Type hints** on functions
- **No business logic in renderer** — receives DTO, outputs PDF

## General

- **No magic strings** — use constants or enums
- **No silent failures** — log or throw
- **No fabricated data** — document as not_found instead
