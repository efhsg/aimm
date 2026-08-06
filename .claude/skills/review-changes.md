---
name: review-changes
description: Read-only evidence-based review of staged or unstaged AIMM changes, with full-file inspection, AIMM rules, financial-integrity checks, confidence tags, blocking severity, and optional design refinement
area: validation
provides:
  - code_review
  - compliance_check
depends_on:
  - rules/coding-standards.md
  - rules/architecture.md
  - rules/security.md
  - rules/testing.md
  - rules/workflow.md
---

# Review Changes

Review the current AIMM change set before finalization. Remain read-only: never
edit, stage, commit, push, migrate, or automatically fix findings.

## Inputs

- optional path or description narrowing the approved review scope;
- optional `staged` to review the index only;
- optional `interactive` to approve the review plan and permit a later design
  phase. Default mode is autonomous Phase 1.

Output in the requester's language.

## Evidence and scope

Resolve the active AIMM root and require a healthy HEAD with no unresolved merge
state. Read `git status --porcelain`, the applicable diff, and the complete
contents of every in-scope changed file, including untracked files. Preserve and
name unexplained out-of-scope changes without reviewing or modifying them.

For a deleted path, read its complete last tracked version with
`git show HEAD:<path>` and inspect current callers, references, registrations,
tests, and data or migration consequences of its removal. For a rename, read
both the full `HEAD` version at the old path and the current version at the new
path. If the required historical object cannot be read, report a verification
blocker instead of treating deletion itself as unreadable.

Read relevant callers, references, analogous implementations, mapped tests, and
canonical AIMM rules. Base findings on current full-file content, not only the
diff or an earlier run. When a file or required context cannot be read fully,
report a verification blocker or tag the bounded claim `needs-verification`;
never issue high confidence from partial evidence.

## Phase 0 — plan

Classify the scope as PHP/backend, frontend, migration, docs, configuration, or
mixed. Select only applicable checks. If no changes exist, report that and stop
without a verdict.

In interactive mode, show the file inventory, change type, selected checks, and
priority focus, then wait:

```text
Plan uitvoeren / Scope aanpassen / Stoppen?
```

Autonomous mode records the same plan and proceeds without a gate.

## Phase 1 — defect detection

Review every in-scope file for applicable concerns:

1. **Correctness:** behavior, contracts, edge cases, error paths, repeated runs,
   and cross-file callers.
2. **AIMM standards:** use only current `.claude/rules/`; never import a
   conflicting PromptManager convention.
3. **Architecture:** approved folder taxonomy, thin controllers, typed models,
   ActiveQuery rules, immutable DTOs, and dependency direction.
4. **Security:** access ownership, input boundaries, secrets, and untrusted
   external content.
5. **Financial integrity:** where relevant, verify source provenance, source
   conflicts, reporting period, unit/currency, transformations, missing or stale
   values, determinism, and fail-closed gates.
6. **Tests:** mapped coverage for changed behavior and critical failure paths.
   Review structure here; execution belongs to finalization.
7. **Documentation/configuration/UI:** syntax, links, registrations, secret
   leakage, documentation impact, and user-visible regressions as applicable.
8. **Environment:** distinguish AIMM host checks from runner-safe inspection.
   An unavailable host check is a handoff, never a pass.

For docs-only changes, restrict review to content correctness, contract
consistency, formatting, links, and registrations. Do not manufacture PHP or
runtime findings. Do not report unchanged legacy code unless the current change
causes or exposes the defect.

## Findings and blocking policy

Every finding contains:

- exact `path:line`;
- severity `Critical`, `High`, `Medium`, or `Low`;
- confidence `high-confidence` or `needs-verification`;
- concrete AIMM rule or code evidence;
- bounded repair direction.

Use these meanings:

| Severity | Meaning | Finalization |
|----------|---------|--------------|
| Critical | Security, corruption, fabricated financial data, broken provenance or ownership | blocked |
| High | Functional defect, data-integrity failure, material architecture violation | blocked |
| Medium | Required-test gap, contract violation, relevant standards deviation | blocked |
| Low | Readability, naming, or documentation refinement | advisory |

Critical, High, and Medium remain open until repaired or dismissed with concrete
counterevidence that disproves the claim. Record that evidence and visible
reclassification or dismissal; preference or general risk acceptance is not
enough.

Stop after Phase 1 while any blocking finding remains.

## Phase 2 — optional design refinement

Only after Phase 1 has no open Critical, High, or Medium finding, and only after
the requester explicitly chooses `Designreview`, re-read the files for SOLID,
DRY, YAGNI, naming, parameter ordering, and abstraction consistency. Report only
Low advisory refinements and stop when the goal is met.

## Output

```markdown
# Reviewoverzicht

**Files beoordeeld:** N
**Scopegrens:** ...
**Fase:** 1 | 2
**Status:** PASS | PASS WITH COMMENTS | NEEDS CHANGES

## Bevindingen
### Critical
- geen | `path:line` — ... — confidence — bewijs — herstelrichting
### High
...
### Medium
...
### Low
...

## Validatiehandoff
- pass | fail | maintainer handoff required — bewijs of exact commando

## Aanbevelingen
1. ...
```

When blocking findings remain, end with:

```text
Bevindingen herstellen / Tegenbewijs bespreken / Stoppen?
```

When Phase 1 is clean, end with:

```text
Finaliseren / Designreview / Stoppen?
```

## Completion

- Every in-scope changed file was inventoried and read completely.
- Applicable callers, rules, tests, financial boundaries, and environment limits
  were checked.
- Findings contain location, severity, confidence, evidence, and repair direction.
- Finalization is blocked for every open Critical, High, or Medium finding.
- Any dismissal records concrete counterevidence.
- No repository, Git, database, or external state changed.
