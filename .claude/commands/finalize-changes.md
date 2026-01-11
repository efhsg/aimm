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

- Ignore unrelated file changes. Leave these files unchanged.
- Always ignore `.claude/screenshots/` and never stage or commit it.

### 2. Check rules compliance

Read and verify compliance with:
- `.claude/rules/coding-standards.md`
- `.claude/rules/architecture.md`

Report any violations found in changed files.

### 3. Run linter

See `.claude/config/project.md` for linter command.

```bash
docker exec aimm_yii vendor/bin/php-cs-fixer fix
```

### 4. Run relevant tests

See `.claude/config/project.md` for test path mappings.

Map changed source files to test files by replacing `src` with `tests/unit`.

```bash
# Run all unit tests
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit

# Run specific test file
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit tests/unit/path/FooTest.php

# Run test directory
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit tests/unit/handlers/foo/
```

**Note:** Codeception accepts only one test path per command. Run separate commands for multiple paths.

If tests fail, stop and report.

### 5. Check documentation

Review changes and determine if site documentation (`site/`) needs updating:

- New features → document in relevant page
- New CLI commands → update `cli-usage.md`
- Configuration changes → update `configuration.md`
- Architecture changes → update `architecture.md`
- New dependencies → update `tech-stack.md`

If documentation updates are needed:
1. Make the updates
2. Rebuild docs: `npm run docs:build`

### 6. Prepare commit

```bash
git add -A
git status
git diff --staged
```

Suggest commit message per `.claude/rules/commits.md`.

**STOP.** Display the suggested commit message and ask for confirmation.
**DO NOT** run `git commit` until the user approves.

## Task

$ARGUMENTS
