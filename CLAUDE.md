# AGENTS_CLAUDE.md (Claude CLI — Opus 4.5)

This file defines **global operating rules for Claude CLI agents** (Opus 4.5) working in this repository.
It complements `AGENTS.md` by adding Claude-CLI-specific execution guidance.

> If `AGENTS.md` exists, follow it first. This file adds **runner-specific** constraints and best practices.

## 1) Prime directive

Claude must behave like a disciplined contributor:
- Read the repo docs first
- Use skills and rules as the source of truth
- Make minimal, reviewable changes
- Add tests and verification steps

## 2) Source of truth (mandatory read order)

1. `docs/RULES.md` — global guardrails
2. `docs/skills/index.md` — skill catalog
3. Selected skills under `docs/skills/**` — contracts (inputs/outputs/DoD/tests)
4. `AGENTS.md` — if present, it overrides this file where applicable

If a required file is missing (e.g., `docs/RULES.md`), create it **only if needed** for the current task.

## 3) Skill-driven workflow (mandatory)

For every task:
1. Identify required capabilities from the task (verbs + nouns).
2. Select the **smallest set of skills** that covers those capabilities:
   - Prefer higher-level skills that cover end-to-end behavior.
   - Add dependencies only if explicitly required.
3. List selected skills at the top of your response (name + path + why).
4. Implement strictly per skill contracts.
5. Add tests matching each skill’s DoD.
6. If required behavior is missing:
   - Add a new skill doc under `docs/skills/<area>/...`
   - Update `docs/skills/index.md`
   - Then implement using the new skill

## 4) Claude CLI operating mode

### 4.1 Interaction style
- Claude should be **explicit and structured**.
- Prefer short planning + concrete diffs over long essays.
- Avoid inventing project-specific APIs; use what exists in the repo.

### 4.2 File grounding discipline
Claude must ground decisions in repository files:
- If changing code, cite which file(s) you used as the basis.
- If assumptions are required, list them in **Notes** and keep them minimal.

### 4.3 Patch-first mindset
When run via CLI, Claude should prioritize outputs that are easy to apply:
- Prefer unified diffs, or file-by-file “before/after” blocks.
- Keep changes localized; avoid sweeping refactors.

### 4.4 No secrets, no artifacts
- Never print or commit secrets.
- Do not generate large binaries or committed runtime artifacts unless explicitly required.

## 5) Response format (strict)

Claude must respond in this structure:

### Selected skills
- `<skill-name>` — `docs/skills/.../file.md` — one-line reason

### Plan
1. …
2. …

### Implementation
- Files changed:
  - `path/to/file` — summary
- Patches:
  - Provide diffs or clearly delimited file contents

### Tests
- `tests/...` — scenarios covered
- How to run:
  - commands to run tests locally (or in Docker)

### Verification
- commands that prove the change works (smoke tests)

### Notes
- Assumptions
- Edge cases
- Rollout/migration notes

## 6) Quality gates for Claude output

Before finalizing, Claude must check:
- Skill invariants were not violated
- Authorization/scope rules apply (when relevant)
- Tests cover the change (including regression tests for bugs)
- Commands to verify are provided and plausible
- No contradictory instructions were introduced

## 7) Definition of Done (Claude agent behavior)

Claude has complied if:
- It read RULES + SKILLS index (+ AGENTS.md if present)
- It selected a minimal set of skills and named them
- It implemented strictly per skill contracts
- It provided patches/diffs suitable for CLI application
- It included tests + verification commands
- It documented files changed and key assumptions
