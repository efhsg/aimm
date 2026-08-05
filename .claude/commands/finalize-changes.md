---
allowed-tools: Bash, Read, Glob, Grep, Edit
description: Validate changes, run linter and tests, prepare commit (project)
---

# Finalize Changes

Read `CLAUDE.md`, `.claude/rules/workflow.md`, and
`.claude/config/project.md` first. Determine whether the active environment is
an AIMM host agent or the PromptManager runner; never substitute one
environment's commands for the other.

## Steps

### 1. Identify changed files

```bash
git status --porcelain
```

- Ignore unrelated file changes. Leave these files unchanged.
- Always ignore `.claude/screenshots/` and never stage or commit it.
- Record the active root, branch, and approved file scope.

### 2. Check rules compliance

Read the rules and verify changed files comply:
- `.claude/rules/coding-standards.md` — PHP standards, type hints, strict_types
- `.claude/rules/architecture.md` — Folder taxonomy, banned patterns, Model/Query/DTO structure

**Verify by file type:**

| Changed files in | Check section in |
|------------------|------------------|
| `queries/*.php` | architecture.md → "Query Classes" |
| `models/*.php` | architecture.md → "ActiveRecord Models" |
| `dto/*.php` | architecture.md → "DTOs" |
| `controllers/*.php` | coding-standards.md → "No business logic in controllers" |
| `migrations/*.php` | architecture.md → "ActiveRecord Models" (ensure Model+Query exist) |
| Any `.php` file | coding-standards.md → "PHP" section |
| Any new folder | architecture.md → "Banned for New Code" |

**Automated checks (all should return empty for compliant code):**
```bash
# Missing strict_types
grep -rL "declare(strict_types=1)" yii/src/**/*.php

# Banned folders exist
ls -d yii/src/services yii/src/helpers yii/src/utils yii/src/misc 2>/dev/null

# Raw SQL in Query classes
grep -l "yii\\\\db\\\\Connection" yii/src/queries/*.php

# Query classes not extending ActiveQuery
grep -L "extends ActiveQuery" yii/src/queries/*.php

# DTOs not readonly
grep -L "readonly class" yii/src/dto/**/*.php 2>/dev/null
```

Report any violations found.

### 3. Run linter

On an AIMM host, use the canonical wrapper from `yii/`:

```bash
../php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php src
```

In the PromptManager runner, do not run AIMM PHP. Record the missing PHP >=8.5
and Docker capabilities and add the command above to the maintainer handoff.

### 4. Run relevant tests

On an AIMM host, use `.claude/config/project.md` for test path mappings.

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

In the PromptManager runner, do not call local Codeception and do not simulate a
passing result. Add the exact relevant command or full unit command to the
maintainer handoff and mark runtime validation pending.

### 5. Check documentation

Review changes and determine if site documentation (`site/`) needs updating:

- New features → document in relevant page
- New CLI commands → update `cli-usage.md`
- Configuration changes → update `configuration.md`
- Architecture changes → update `architecture.md`
- New dependencies → update `tech-stack.md`

If documentation updates are needed:
1. Make the updates
2. On the host, rebuild docs: `npm run docs:build`
3. In the PromptManager runner, hand off that command without claiming it ran

### 6. Prepare commit

```bash
git add -- <approved-paths>
git status
git diff --staged
```

Never use `git add -A`. Leave unrelated tracked, untracked, staged, and unstaged
changes untouched. If the staged diff contains an unapproved path, stop before
suggesting a commit.

Suggest commit message per `.claude/rules/commits.md`.

Report validation as:

- `pass` only when every applicable command ran successfully;
- `fail` when a check ran and failed;
- `maintainer handoff required` when the PromptManager runner lacks the required
  runtime, including exact commands and no success claim.

**STOP.** Display the suggested commit message and ask for confirmation.
**DO NOT** run `git commit` until the user approves.

## Task

$ARGUMENTS
