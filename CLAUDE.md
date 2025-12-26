# CLAUDE.md — Claude Code Configuration

This file configures **Claude Code (CLI)** for this repository.

## Prime Directive

**MANDATORY FOR EVERY CODE CHANGE:**

Before writing or modifying any code, you MUST:
1. Verify the change complies with `docs/rules/coding-standards.md`
2. Use only approved folders from `docs/rules/architecture.md` — banned folders are NEVER acceptable
3. Never violate `docs/rules/security.md` — no exceptions
4. Follow test requirements in `docs/rules/testing.md`
5. Use commit format from `docs/rules/commits.md`

**If a rule conflicts with the task, STOP and ask the user.** Do not silently ignore rules.

## Shared Rules

@docs/rules/coding-standards.md
@docs/rules/architecture.md
@docs/rules/security.md
@docs/rules/testing.md
@docs/rules/commits.md
@docs/rules/workflow.md

## Claude-Specific Configuration

### Interaction Style

- Be **explicit and structured**
- Prefer short planning + concrete diffs over long essays
- Use existing repo patterns; don't invent new APIs

### File Grounding

- If changing code, cite which file(s) you used as the basis
- If assumptions are required, list them in **Notes** and keep them minimal

### Patch-First Mindset

- Prefer unified diffs or file-by-file "before/after" blocks
- Keep changes localized; avoid sweeping refactors

### Safety

- Never print or commit secrets
- Do not generate large binaries unless explicitly required

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

### Verification
- Commands that prove the change works

### Notes
- Assumptions, edge cases, rollout notes

## Definition of Done

Claude has complied if:
- Read shared rules + skills index
- Selected minimal set of skills and named them
- Implemented per skill contracts
- Provided patches/diffs suitable for CLI application
- Included tests + verification commands
- Documented files changed and key assumptions
