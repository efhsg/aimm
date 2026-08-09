---
name: finalize-changes
description: Fail-closed AIMM pre-commit finalization that proves scope and artifact eligibility, runs supported validation, stops for required host evidence, stages only approved paths, and suggests a commit without committing or pushing
area: workflow
provides:
  - change_finalization
depends_on:
  - skills/validate-spec.md
  - skills/review-changes.md
  - rules/coding-standards.md
  - rules/architecture.md
  - rules/security.md
  - rules/testing.md
  - rules/commits.md
  - rules/workflow.md
  - config/project.md
---

# Finalize Changes

Prepare an approved AIMM change set for commit. Fail closed: never call an
unavailable or failed check a pass, never stage an unapproved path, and never
commit, push, archive, migrate, or broaden scope automatically.

## 1. Establish scope before mutation

Read `CLAUDE.md`, `.claude/config/project.md`, relevant rules, and:

```bash
git rev-parse --show-toplevel
git branch --show-current
git rev-parse HEAD
git status --porcelain
git diff
git diff --cached
```

Show the active root, branch, HEAD, explicitly approved paths, and every changed
path outside that scope. Include tracked, untracked, staged, and unstaged files.
If approved scope is missing or ambiguous, ask for exact paths. If an unrelated
path is already staged, leave the index unchanged and stop until scope is
resolved; do not unstage user work silently.

Ignore `.claude/screenshots/` for staging and leave it untouched.

## 2. Classify the durable artifact contract

Classify the change as exactly one primary type:

| Type | Required artifact |
|------|-------------------|
| Feature product implementation | Exactly one linked feature spec, mechanically valid and `accepted` |
| Feature documentation only | The changed spec; `draft`, `in-review`, or `accepted` may be prepared |
| Bugfix | AIMM bug artifact when the canonical workflow requires it; no artificial feature spec |
| Behaviorless refactor | Evidence of unchanged behavior; no artificial feature spec |
| Agent configuration only | Approved config scope and the contract of the changed artifact |

Resolve a feature link from an explicit `feature=<slug-or-path>` hint first,
then a touched `.ai/features/{name}/` map, then an unambiguous branch relation.
Zero or multiple candidates block feature product implementation until the
requester names exactly one. Never infer acceptance from a plan or conversation.

Apply current `.claude/skills/validate-spec.md` R1–R12 to every changed spec. For
feature product implementation, the one linked durable spec must pass and have
status `accepted`. A passing `draft` or `in-review` spec may be staged only as
document-only work; state explicitly that it does not authorize implementation.

## 3. Review compliance

Read every approved changed file completely and apply current AIMM rules. Confirm
that no Critical, High, or Medium `/review-changes` finding remains. If current
review evidence is unavailable, perform the same scoped defect checks or stop
and direct the requester to `/review-changes`. Concrete counterevidence is
required for a dismissal.

For approved PHP scope, verify the existing AIMM gates against their owning
rule, which stays authoritative when this list and the rule disagree:

- `declare(strict_types=1)` and file-type formatting — `rules/coding-standards.md`;
- `ActiveQuery` extension and the documented read-only raw-SQL exception —
  `rules/architecture.md`;
- `readonly` DTO classes — `rules/architecture.md`;
- model, query, and mapped tests in scope for every new-table migration —
  `rules/architecture.md` and `rules/testing.md`;
- banned taxonomy names, checked only for newly introduced folders —
  `rules/architecture.md`;
- mapped tests for changed behavior and critical failure paths —
  `rules/testing.md`.

Determine documentation impact without editing automatically: new features,
CLI commands, configuration, architecture, or dependencies require their
relevant `site/` page when AIMM's documentation contract says so. Missing
required documentation is a blocking finding, not permission to expand scope.

## 4. Build the validation matrix

For each applicable check record exactly `pass`, `fail`, or `maintainer handoff
required`, plus command and evidence.

In the PromptManager runner, execute only runner-safe checks documented in
`.claude/config/project.md`, including as applicable:

```bash
git diff --check
jq empty .claude/settings.json
```

Do not run local AIMM PHP or simulate Docker. For PHP changes, hand off the
linter and Codeception commands exactly as written in
`.claude/config/project.md` § Commands. Quote them verbatim from that file at
handoff time; never reproduce a remembered or adapted variant here, because
project configuration owns those commands and this skill drifts the moment it
keeps its own copy.

Use the narrower mapped test commands from the same section when the approved
source scope permits them; run multiple test paths sequentially. For a
site-documentation change, use the documented host documentation-build handoff.
For a migration change, require the exact maintainer validation and readback
from project configuration; do not apply a migration from this skill.

A failed supported check blocks. Any applicable blocking host handoff also
blocks before staging and commit-message proposal. Report the exact commands and
wait for returned evidence; on resume, preserve the same root, HEAD, and scope or
restart finalization when they changed.

## 5. Stage only after all blocking checks pass

After every applicable blocking validation is `pass`, stage only literal,
reviewed paths:

```bash
git add -- <approved-paths>
git status --short
git diff --cached --check
git diff --cached --name-status
git diff --cached
```

Never use `git add -A`, a broad glob, or an unresolved variable. Verify that the
complete staged set contains only approved paths. If not, stop without removing
pre-existing staged work.

## 6. Propose the commit

Suggest one message matching `.claude/rules/commits.md` only after staged
readback succeeds. Do not run `git commit` or `git push`.

```markdown
# Finalisatieoverzicht

**Root / branch / HEAD:** ...
**Goedgekeurde paden:** ...
**Afwijkende wijzigingen:** ...
**Wijzigingstype en artefact:** ...

## Validatie
- {check}: {pass | fail | maintainer handoff required} — {bewijs/commando}

## Staging
- Alleen goedgekeurde paden: ja
- Cached diff teruggelezen: ja

**Commitvoorstel:** `{type(scope): imperative description}`
```

End a blocking handoff with:

```text
Bewijs aanleveren / Scope aanpassen / Stoppen?
```

End a successfully staged result with:

```text
Committen / Diff bekijken / Stoppen?
```

Then wait. Only `Committen` authorizes starting `/cp` as a separate approved
action, and only `Bewijs aanleveren` authorizes rerunning the blocked validation
with maintainer evidence. This skill never commits, pushes, or stages beyond the
approved paths, whichever option the user selects.

## Completion

- Root, branch, HEAD, full worktree, and approved scope were shown before mutation.
- Changed specs passed R1–R12; feature product code has exactly one valid
  `accepted` spec.
- Every applicable validation has honest status and blocking handoffs completed
  before staging.
- The full cached diff contains only approved paths and was read back.
- A compliant commit message was proposed.
- No commit, push, archive, migration, product-data, or external mutation ran.
