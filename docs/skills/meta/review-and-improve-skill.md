---
name: review-and-improve-skill
description: Review an existing SKILL.md and produce an improved version with clearer contracts, tighter scope, better invariants, and actionable DoD/tests. Use when a skill doc is drafted (e.g., setup-project) and needs quality control before indexing.
model: GPT-5.2 Thinking
area: meta
provides:
  - skill_quality_review
  - improved_skill_markdown
  - change_list
depends_on:
  - docs/RULES.md
---

# ReviewAndImproveSkill

Review a skill document and rewrite it to be crisp, consistent, and enforceable by humans + AI agents.

## When to use
- A new skill was drafted and should be standardized before adding to `docs/skills/index.md`.
- A skill is too broad, ambiguous, contradictory, or missing contracts/tests.
- A skill includes global conventions that should belong in `docs/RULES.md`.

## Inputs
- `skillPath`: path to the skill markdown file (e.g., `docs/skills/meta/setup-project.md`)
- `context` (optional):
  - repo structure assumptions
  - target OS/platform constraints
  - any “must-have” tooling (e.g., Docker, Composer, Python)

## Outputs
1. **Review summary** with:
   - major issues (blocking)
   - minor issues (improvements)
   - risks (security/operational)
2. **Rewritten skill markdown** (ready to commit)
3. **Diff-style change list** (what changed and why)

## Non-goals
- Do not implement app features.
- Do not invent architecture beyond what the skill needs.
- Do not move project-wide conventions into the skill if they belong in `docs/RULES.md`.

## Review checklist (apply in this order)

### 1) Scope and intent
- Is this truly a **skill** (atomic capability) vs a **playbook** (broad guidance)?
  - If it’s a playbook, recommend moving it out of skills (or splitting into multiple skills).
- Is the skill name/action verb precise (e.g., `setup-project` is OK)?

### 2) Contract quality
- Are **inputs** explicit and minimal?
- Are **outputs** concrete artifacts (files created, commands runnable)?
- Are side effects stated (containers created, ports opened, DB data volume)?

### 3) Determinism and safety
- Are commands idempotent (running twice doesn’t corrupt state)?
- Are destructive steps clearly labeled (e.g., deleting volumes)?
- Are secrets handled correctly (`.env.example` only; `.env` gitignored)?

### 4) Alignment with RULES.md
- If the skill includes global conventions (PSR-12, imports, folder taxonomy), ensure it references `docs/RULES.md` instead of duplicating.
- Ensure it does not contradict existing rules.

### 5) Completeness for “run it now”
- Does the doc include every file it claims exists?
- Are all paths consistent?
- Are required chmod steps included (scripts + yii entrypoints)?
- Are compose services functional (env vars valid, volumes correct, networks coherent)?

### 6) Operational correctness (Docker-specific)
- Check container responsibilities are clean:
  - PHP container: composer, Yii entrypoints, Xdebug optional
  - Python container: renderer deps, mounted runtime
  - MySQL: init scripts, volumes, ports
  - Nginx: template substitution works
- Check fragile points:
  - envsubst usage
  - user permissions on bind mounts
  - MySQL init scripts executable + correct line endings
  - DB init logic minimal privilege (avoid `GRANT ALL ON *.*` unless intended)

### 7) Definition of Done and verification
- DoD must be testable with commands (copy/paste).
- Include a minimal smoke test that proves:
  - containers start
  - PHP runs Yii command
  - DB connection works
  - Python imports dependencies

## Improvement algorithm
1. **Read** the existing skill and extract:
   - claimed outputs
   - steps and created files
   - implicit assumptions
2. **Normalize structure** (keep consistent headings):
   - Overview
   - Prerequisites
   - Outputs
   - Steps (numbered, copy/paste blocks)
   - Verification
   - Definition of Done
   - Common Commands
   - Troubleshooting
3. **Tighten scope**:
   - remove feature work
   - split if it contains unrelated domains (e.g., “setup + add first industry”)
4. **Make it enforceable**:
   - add explicit file lists
   - ensure commands are idempotent or warn clearly
   - add verification commands after each major step
5. **Rewrite for agents**:
   - avoid ambiguity (“create files” → list exact file names and contents)
   - ensure no TODO placeholders in critical path (allowed only after smoke test passes)
6. **Output**: revised skill + change list + top risks.

## Output format (strict)

### Review summary
- Blockers:
  - …
- Improvements:
  - …
- Risks:
  - …

### Revised skill (ready to commit)
```md
<full rewritten skill markdown>
```

### Change list
- CHG: …
- FIX: …
- DOC: …

## Definition of Done (for this meta-skill)
- The rewritten skill:
  - has a clear contract (inputs/outputs)
  - has runnable, ordered steps
  - includes verification commands
  - has a DoD that can be checked quickly
  - references `docs/RULES.md` for global conventions (does not duplicate them)
  - removes ambiguity and reduces footguns (permissions, secrets, destructive ops)
