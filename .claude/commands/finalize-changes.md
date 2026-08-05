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

**Runner-safe inventory and whitespace checks:**
```bash
git diff --check
git diff --name-only --diff-filter=ACMR HEAD -- yii/src yii/migrations yii/tests
git ls-files --others --exclude-standard -- yii/src yii/migrations yii/tests
```

Read every listed PHP file and apply the file-type checks above. In particular:

- require `declare(strict_types=1)` in each changed PHP file;
- classify changed files in `queries/` before checking them: model queries must
  extend `ActiveQuery`; documented read-only reporting queries may use the
  architecture rule's raw-SQL exception;
- require changed DTO classes to be `readonly`;
- for each new table migration, verify the corresponding ActiveRecord model,
  ActiveQuery class, and mapped unit tests are included in the approved scope;
- inspect only new folder paths for banned taxonomy names.

Report violations without treating unchanged legacy files as findings.

### 3. Run linter

On an AIMM host, run the canonical non-mutating source check under **Linter** in
`.claude/config/project.md`. In the PromptManager runner, follow
`.claude/rules/workflow.md` and hand that exact command to the maintainer.

### 4. Run relevant tests

On an AIMM host, map changed source files with the table in
`.claude/config/project.md` and run the canonical relevant test command from
that file. Run separate commands sequentially when multiple paths are needed.

If tests fail, stop and report.

In the PromptManager runner, follow `.claude/rules/workflow.md`. Add the exact
relevant command from `.claude/config/project.md` to the maintainer handoff and
mark runtime validation pending.

### 5. Check documentation

Review changes and determine if site documentation (`site/`) needs updating:

- New features → document in relevant page
- New CLI commands → update `cli-usage.md`
- Configuration changes → update `configuration.md`
- Architecture changes → update `architecture.md`
- New dependencies → update `tech-stack.md`

If documentation updates are needed:
1. Make the updates
2. On the host, run the canonical documentation build in
   `.claude/config/project.md`
3. In the PromptManager runner, use the documentation-build handoff from
   `.claude/config/project.md` without claiming it ran

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
