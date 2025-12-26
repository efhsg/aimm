# Multi-Agent Rule Modularization Template

Reusable prompt for setting up consistent AI agent configuration across projects.

## Goal

Restructure project rules into modular files that multiple AI agents (Claude, Codex, Gemini, etc.) can reference consistently.

## Structure

```
docs/rules/
├── coding-standards.md   # Language-specific conventions
├── architecture.md       # Folder taxonomy, allowed/banned patterns
├── security.md           # Access control, secrets, data provenance
├── testing.md            # Coverage requirements, naming conventions
├── commits.md            # Commit message format (conventional commits)
└── workflow.md           # Development process, code review checklist

CLAUDE.md                 # Claude Code config, references @docs/rules/*
AGENTS.md                 # OpenAI Codex config, references docs/rules/*
GEMINI.md                 # Google Gemini config, references docs/rules/*
```

## Agent Config Template

Each agent config file should include these sections:

### 1. Role

```markdown
## Role

You are a **[Role Title]** specializing in [domain].

**Expertise:**
- [Primary stack] — [what it's used for]
- [Secondary stack] — [what it's used for]
- [Domain knowledge]

**Responsibilities:**
- Write clean, tested, production-ready code
- Follow existing patterns; don't invent new conventions
- [Domain-specific responsibility]
- Ask clarifying questions before making assumptions

**Boundaries:**
- Never commit secrets or credentials
- [Domain-specific constraint]
- Stop and ask if a rule conflicts with the task
```

### 2. Prime Directive

```markdown
## Prime Directive

**MANDATORY FOR EVERY CODE CHANGE:**

Before writing or modifying any code, you MUST:
1. Verify the change complies with `docs/rules/coding-standards.md`
2. Use only approved folders from `docs/rules/architecture.md`
3. Never violate `docs/rules/security.md` — no exceptions
4. Follow test requirements in `docs/rules/testing.md`
5. Use commit format from `docs/rules/commits.md`

**If a rule conflicts with the task, STOP and ask the user.**
```

### 3. Shared Rules

```markdown
## Shared Rules

- `docs/rules/coding-standards.md` — Language conventions
- `docs/rules/architecture.md` — Folder taxonomy and banned patterns
- `docs/rules/security.md` — Scope enforcement, data provenance, secrets
- `docs/rules/testing.md` — Coverage requirements and naming conventions
- `docs/rules/commits.md` — Commit message format
- `docs/rules/workflow.md` — Development process and code review
```

### 4. Response Format

```markdown
## Response Format

When implementing tasks, respond with:

### Selected skills
- `<skill-name>` — `docs/skills/.../file.md` — one-line reason

### Plan
1. …

### Implementation
- Files changed:
  - `path/to/file` — summary

### Tests
- `tests/...` — scenarios covered
- How to run: `command`

### Notes
- Assumptions, edge cases, rollout notes
```

### 5. Definition of Done

```markdown
## Definition of Done

Agent has complied if:
- Read shared rules + skills index
- Selected minimal set of skills and named them
- Implemented per skill contracts
- Added tests matching skill DoD (when code changes)
- Documented files changed and key assumptions
```

## Benefits

- **Single source of truth** — Rules defined once in `docs/rules/`
- **Consistent behavior** — All agents follow the same standards
- **Easy maintenance** — Update rules in one place
- **Agent flexibility** — Customize interaction style per agent

## Optional Additions

- `docs/skills/` — Reusable task templates with inputs/outputs/DoD
- `docs/rules/index.md` — Quick reference linking all rule files
- Linter as dev dependency (e.g., `php-cs-fixer`, `eslint`, `black`)
