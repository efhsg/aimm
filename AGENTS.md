# AGENTS.md

This file defines **global operating rules for AI agents** working in this repository.
It is the entry point for agent behavior and must be followed for every task.

## 1) Source of truth

Agents must consult these files in this order:

1. `docs/RULES.md` — global guardrails (coding conventions, architecture rules, testing baseline)
2. `docs/skills/index.md` — skill catalog (what capabilities exist)
3. The selected skill docs under `docs/skills/**` — exact contracts (inputs/outputs/DoD/tests)

If any of these files are missing, the agent should:
- create them if required for the task’s workflow (e.g., `docs/RULES.md` for global rules)
- otherwise proceed with best effort while keeping changes minimal and well-scoped

## 2) Skill-driven workflow (mandatory)

For every task:

1. Read `docs/RULES.md`.
2. Read `docs/skills/index.md`.
3. Select the **smallest set of skills** needed to complete the task.
   - Prefer the highest-level skill that covers the work end-to-end.
   - Add additional skills only if they are explicit dependencies or required capabilities are missing.
4. Follow each selected skill’s contract exactly:
   - inputs/outputs
   - algorithm
   - invariants
   - Definition of Done
   - tests
5. If required behavior is not covered by existing skills:
   - add a new skill file under `docs/skills/<area>/...`
   - update `docs/skills/index.md`
   - then implement using that new skill

## 3) Output format (strict)

Agents must format their response as:

### Selected skills
- `<skill-name>` — `docs/skills/.../file.md` — one-line reason

### Plan
1. …
2. …

### Implementation
- Files changed:
  - `path/to/file` — summary of change

### Tests
- `tests/...` — scenarios covered

### Notes
- Assumptions
- Edge cases
- Migration/rollout notes (if any)

## 4) Engineering conventions (summary)

These are reminders; the authoritative rules are in `docs/RULES.md`.

- PHP: PSR-12 formatting
- Use explicit imports (`use ...`); avoid fully-qualified class names inline
- Do **not** add `declare(strict_types=1);` (project convention)
- No catch-all folders like `helpers/` or `services/`; place code in the correct architectural bucket
- Keep changes minimal and cohesive (avoid unrelated refactors)

## 5) Safety and hygiene

- Do not commit secrets. Keep `.env` out of git.
- Prefer small PRs and isolated commits.
- Do not add large binaries or generated artifacts to the repository unless explicitly required.
- When modifying infrastructure (Docker/CI), include verification steps.

## 6) Definition of Done (agent behavior)

An agent has complied with AGENTS.md if:
- it read RULES + SKILLS index
- it selected a minimal set of skills and named them
- it implemented per the skill contracts
- it added tests matching the skill DoD (when code changes)
- it documented files changed and key assumptions
