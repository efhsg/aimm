# GEMINI.md — Google Gemini Configuration

This file configures **Google Gemini** for this repository.

## Prime Directive

**MANDATORY FOR EVERY CODE CHANGE:**

Before writing or modifying any code, you MUST:
1. Read and comply with `docs/rules/coding-standards.md`
2. Use only approved folders from `docs/rules/architecture.md` — banned folders are NEVER acceptable
3. Never violate `docs/rules/security.md` — no exceptions
4. Follow test requirements in `docs/rules/testing.md`
5. Use commit format from `docs/rules/commits.md`

**If a rule conflicts with the task, STOP and ask the user.** Do not silently ignore rules.

## Shared Rules

Read and follow these rule files:

- `docs/rules/coding-standards.md` — PHP, Python, general conventions
- `docs/rules/architecture.md` — Folder taxonomy and banned patterns
- `docs/rules/security.md` — Scope enforcement, data provenance, secrets
- `docs/rules/testing.md` — Coverage requirements and naming conventions
- `docs/rules/commits.md` — Commit message format
- `docs/rules/workflow.md` — Skill-driven development and code review

## Gemini-Specific Configuration

### Skill-Driven Workflow

For every task:

1. Read `docs/rules/` files first
2. Read `docs/skills/index.md` — skill catalog
3. Select the **smallest set of skills** needed
4. Follow each skill's contract exactly (inputs/outputs/DoD/tests)
5. If behavior isn't covered, create a new skill under `docs/skills/`

## Response Format

When implementing tasks, respond with:

### Selected skills
- `<skill-name>` — `docs/skills/.../file.md` — one-line reason

### Plan
1. …

### Implementation
- Files changed:
  - `path/to/file` — summary of change

### Tests
- `tests/...` — scenarios covered

### Notes
- Assumptions, edge cases, migration notes

## Definition of Done

Agent has complied if:
- Read shared rules + skills index
- Selected minimal set of skills and named them
- Implemented per skill contracts
- Added tests matching skill DoD (when code changes)
- Documented files changed and key assumptions
