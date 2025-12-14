---
name: add-root-readme
description: Add or update the repository root `README.md` to describe the application, dev setup, and key entry points.
area: meta
depends_on:
  - docs/RULES.md
---

# AddRootReadme

Create a concise root `README.md` that explains what this repository is and how to run it locally.

## Contract

### Inputs

- `repoRoot` (directory): repository root

### Outputs

- `README.md` exists at the repository root and includes:
  - What the project does (1–3 sentences)
  - Tech stack / services (Docker Compose: Nginx, PHP-FPM/Yii2, MySQL, Python renderer)
  - Local quick start commands (copy `.env`, start compose, install deps)
  - URLs/ports used (from `.env.example`)
  - Links to documentation entry points under `docs/`

### Safety / invariants

- Do not include secrets. Refer to `.env.example` and instruct copying to `.env`.
- Keep the README app-focused; keep detailed guardrails in `docs/RULES.md`.

## Definition of Done

- `README.md` is present and correct for the current repo layout and `docker-compose.yml`
- Commands are copy-pastable and do not require network access beyond Docker image pulls

