---
allowed-tools: Bash, Read, Glob, Grep, Edit
description: Validate changes, run linter and tests, prepare commit (project)
---

# Finalize Changes

## Steps

### 1. Identify changed files

```bash
git status --porcelain
```

### 2. Check rules compliance

Read and verify compliance with:
- `docs/rules/coding-standards.md`
- `docs/rules/architecture.md`

Report any violations found in changed files.

### 3. Run linter

```bash
make fix-changed
```

### 4. Run relevant tests

Map changed files to test files:
- `src/handlers/Foo.php` → `tests/unit/handlers/FooTest.php`
- `src/queries/Foo.php` → `tests/unit/queries/FooTest.php`
- `src/validators/Foo.php` → `tests/unit/validators/FooTest.php`
- `src/transformers/Foo.php` → `tests/unit/transformers/FooTest.php`
- `src/factories/Foo.php` → `tests/unit/factories/FooTest.php`
- `src/adapters/Foo.php` → `tests/unit/adapters/FooTest.php`
- `src/models/Foo.php` → `tests/unit/models/FooTest.php`
- `src/dto/Foo.php` → `tests/unit/dto/FooTest.php`
- `src/enums/Foo.php` → `tests/unit/enums/FooTest.php`
- `src/exceptions/Foo.php` → (no tests required for simple exceptions)

```bash
# Run all unit tests
docker exec aimm_yii vendor/bin/codecept run unit

# Run specific test file
docker exec aimm_yii vendor/bin/codecept run unit tests/unit/path/FooTest.php
```

If tests fail, stop and report.

### 5. Prepare commit

```bash
git add -A
git status
git diff --staged
```

Suggest commit message per `docs/rules/commits.md`.

Ask user for confirmation before committing.

## Task

$ARGUMENTS
