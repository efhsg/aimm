---
name: add-docs-readme
description: Add or update `docs/README.md` to explain the documentation entry points and how to contribute to docs/skills/prompts.
area: meta
depends_on:
  - docs/RULES.md
---

# AddDocsReadme

Create a concise `docs/README.md` that orients developers/agents to the documentation structure.

## Contract

### Inputs

- `repoRoot` (directory): repository root

### Outputs

- `docs/README.md` exists and documents:
  - `docs/RULES.md`
  - `docs/skills/index.md`
  - `docs/prompts/`

### Safety / invariants

- Do not include secrets or environment-specific values.
- Keep it short and high-signal; avoid duplicating `docs/RULES.md`.

## Definition of Done

- `docs/README.md` is present and points to the three primary entry points
- Links/paths are correct and workspace-relative

