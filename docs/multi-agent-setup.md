# Multi-Agent Rule Modularization Template

Reusable template for setting up consistent AI agent configuration across projects.

## Architecture: Two-Layer Approach

```
project/
├── docs/
│   └── rules/                    # SHARED LAYER (tool-agnostic)
│       ├── coding-standards.md
│       ├── architecture.md
│       ├── security.md
│       ├── testing.md
│       ├── commits.md
│       └── workflow.md
│
├── CLAUDE.md                     # ENTRY LAYER: Claude wrapper
├── AGENTS.md                     # ENTRY LAYER: Codex wrapper
├── GEMINI.md                     # ENTRY LAYER: Gemini wrapper
│
└── .claude/
    └── rules -> ../docs/rules    # Symlink for Claude directory access
```

### Layer 1: Shared Rules (`docs/rules/`)

Tool-agnostic markdown containing your actual standards. Write these as plain documentation that any agent can understand.

| File | Purpose |
|------|---------|
| `coding-standards.md` | Language conventions (PSR-12, PEP 8, etc.) |
| `architecture.md` | Folder taxonomy, allowed/banned patterns |
| `security.md` | Access control, secrets, data provenance |
| `testing.md` | Coverage requirements, naming conventions |
| `commits.md` | Commit message format |
| `workflow.md` | Development process, code review |

### Layer 2: Entry Files (Tool-Specific Wrappers)

Thin wrappers that:
- Import shared rules using tool-specific syntax
- Add tool-specific behavioral instructions
- Include workarounds for tool limitations

## Tool-Specific Import Syntax

### Claude (`CLAUDE.md`)

Claude Code supports `@` imports that inline file contents:

```markdown
# Project: [Name]

## Role
[Role definition]

## Tool Configuration
- Use Bash for git operations
- Prefer Edit over sed/awk
- Use Glob/Grep instead of find/grep commands

## Shared Rules
@docs/rules/coding-standards.md
@docs/rules/architecture.md
@docs/rules/security.md
@docs/rules/testing.md
@docs/rules/commits.md
@docs/rules/workflow.md

## Claude-Specific Notes
[Any Claude-specific instructions]
```

**Optional:** Create `.claude/rules` symlink for directory access:
```bash
mkdir -p .claude
ln -s ../docs/rules .claude/rules
```

### Codex (`AGENTS.md`)

Codex doesn't support `@` imports. Options:

**Option A: Reference files (agent reads them)**
```markdown
# Project: [Name]

## Role
[Role definition]

## Shared Rules

Read and follow these rule files:
- `docs/rules/coding-standards.md` — Language conventions
- `docs/rules/architecture.md` — Folder taxonomy
- `docs/rules/security.md` — Security policies
- `docs/rules/testing.md` — Test requirements
- `docs/rules/commits.md` — Commit format
- `docs/rules/workflow.md` — Development workflow

## Codex-Specific Notes
[Any Codex-specific instructions]
```

**Option B: Build script to concatenate**
```bash
cat docs/rules/*.md > AGENTS.generated.md
```

### Gemini (`GEMINI.md`)

Gemini supports `@` imports similar to Claude:

```markdown
# Project: [Name]

## Role
[Role definition]

@docs/rules/coding-standards.md
@docs/rules/architecture.md
@docs/rules/security.md
@docs/rules/testing.md
@docs/rules/commits.md
@docs/rules/workflow.md

## Gemini-Specific Notes
[Any Gemini-specific instructions]
```

## Agent Config Sections

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

### 3. Tool Configuration (Tool-Specific)

```markdown
## Tool Configuration

- Use Bash for git operations
- Prefer Edit over sed/awk
- Use Glob/Grep instead of find/grep commands
- [Other tool-specific preferences]
```

### 4. Response Format

```markdown
## Response Format

When implementing tasks, respond with:

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
- Read and followed shared rules
- Implemented per requirements
- Added tests (when code changes)
- Documented files changed and key assumptions
```

## Benefits

- **Single source of truth** — Rules defined once in `docs/rules/`
- **Consistent behavior** — All agents follow the same standards
- **Easy maintenance** — Update rules in one place
- **Agent flexibility** — Customize tool-specific behavior in wrappers

## Optional Additions

- `docs/skills/` — Reusable task templates with inputs/outputs/DoD
- `docs/rules/index.md` — Quick reference linking all rule files
- Linter as dev dependency (e.g., `php-cs-fixer`, `eslint`, `black`)
- Build script to generate concatenated config for tools without imports
