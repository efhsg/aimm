---
name: validate-spec
description: Read-only deterministic validation of AIMM specs against the AIMM-PRD-1 R1-R12 contract
area: validation
provides:
  - aimm_prd_mechanical_validation
depends_on:
  - templates/spec-prd.md
---

# Validate Spec

Mechanically validate AIMM functional specs. Apply only the fixed checks below;
do not improve, rewrite, or semantically judge the document.

## Inputs and boundary

Accept exactly one of:

- one `.ai/features/{name}/spec.md` path;
- `.claude/templates/spec-prd.md`;
- `--all`, meaning the template and all `.ai/features/*/spec.md` files.

When no target is supplied, use `--all`. Reject multiple targets, missing files,
symlinks escaping the active AIMM worktree, and spec paths outside the direct
feature-root pattern. Never write files or change Git state.

Supported validator revision: `AIMM-PRD-1`.

## Deterministic processing

1. Resolve targets lexicographically and process the template first for `--all`.
2. Read each target completely. A read or parse problem is a failed validation,
   never a pass.
3. Preserve line numbers but ignore HTML-comment bodies for content counts.
4. For R9 and R10, ignore fenced code blocks delimited by matching triple
   backticks or tildes. An unclosed fence is a processing failure.
5. For R10, scan every line from line 1 through the first exact `---` separator
   before removing inline-code spans. In the remaining body, ignore inline-code
   spans so generic notation such as `.ai/features/<name>/` is not mistaken for
   unfinished author work.
6. Evaluate R1 through R12 in numeric order. Within a rule, sort findings by
   target path and then line number.
7. Run the R11 uniqueness check against all `.ai/features/*/spec.md` files even
   when validating one spec. Name every conflicting path and treat every member
   of the collision as blocked; do not modify any of them.

Use the first content line of a section as its location when the problem is a
missing value. Use line 1 when the entire file or required metadata is missing.

## AIMM-PRD-1 rules

| ID | Mechanical check |
|----|------------------|
| R1 | H2 sections `1. Doel en context` through `7. Impact op bestaande flows en data`, each with the literal suffix `**[verplicht]**`, exist once and in numeric order. |
| R2 | §1 contains `**Kernbericht (één zin):**` followed by non-placeholder text and at least one separate non-placeholder context paragraph. In template mode the documented placeholders satisfy this shape. |
| R3 | §2 contains the three-column actor table header and at least one non-separator data row. Template placeholders satisfy the row only in template mode. |
| R4 | §3 contains H3 headings `In scope` and `Niet in scope`, each followed by at least one bullet. Every non-scope bullet contains ` — ` with non-placeholder text on both sides; template placeholders are allowed in template mode. |
| R5 | §4 contains at least three sequential numbered steps beginning with `1.`, `2.`, and `3.`. Placeholder steps count only in template mode. |
| R6 | §5 contains the two-column edge-case table header and at least three non-separator data rows. Placeholder rows count only in template mode. |
| R7 | §6 contains at least three bullets named `AC-{slug}{N}`, where `{slug}` equals the metadata slug and N is a positive integer unique within the spec. Each contains one observable verb: `toont`, `produceert`, `faalt`, `blokkeert`, `is zichtbaar`, `levert`, `registreert`, `weigert`, `passeert`, `meldt`, `vermeldt`, or `bevat`. The literal template forms `AC-<slug>1`, `AC-<slug>2`, and `AC-<slug>3` are allowed only in template mode. |
| R8 | §7 contains non-empty bullet values for `**Raakt:**`, `**Blokkeert:**`, `**Blokkeert-door:**`, `**Effect op bestaande data:**`, and `**Effect op bestaande gebruikers:**`. Template placeholders are allowed in template mode. |
| R9 | Outside fenced examples, none of the fixed patterns below matches. Do not add inferred implementation categories during a run. |
| R10 | Metadata contains exactly one valid status (`draft`, `in-review`, or `accepted`) and exactly one supported contract revision. Apply the fixed unfinished-content checks below. Only a mechanically passing `accepted` spec can authorize implementation. |
| R11 | `Feature-slug` is present and unique across `.ai/features/*/spec.md`. This rule is not applicable to the literal template placeholder. |
| R12 | `Feature-slug` matches `^[a-z][a-z0-9-]{2,23}$`. In template mode only the literal `<slug>` is accepted instead. |

### R9 fixed patterns

Apply this closed, case-sensitive regex list line by line. A match is one R9
finding at the match line. Do not flag any other form under R9 during that run.

```text
\bclass\s+[A-Z]\w*\s+(extends|implements|\{)
^\s*namespace\s+[\w\\]+\s*;
^\s*use\s+[A-Z]\w*(\\[A-Z]\w*)+\s*;?\s*$
\bfunction\s+\w+\s*\([^)]*\)\s*[{\:]
\b(public|private|protected)\s+function\s+\w+\s*\(
\bCREATE\s+(TABLE|INDEX|VIEW|SCHEMA)\b
\bALTER\s+TABLE\b
\bDROP\s+(TABLE|INDEX|VIEW)\b
\bINSERT\s+INTO\b
\bUPDATE\s+\w+\s+SET\b
\bDELETE\s+FROM\b
\bSELECT\s+.*\s+FROM\s+\w+
\bcomposer\s+(require|update|install|remove)\b
\bnpm\s+(install|i|add|uninstall)\s+[-\w@/]+
\byarn\s+add\s+[-\w@/]+
\bpip\s+install\s+\w
^\s*import\s+.+\s+from\s+['"]
^\s*const\s+\w+\s*=\s*require\s*\(
\bsafeUp\s*\(\s*\)
\bsafeDown\s*\(\s*\)
\$this->createTable\b
\$this->addColumn\b
^\s*[A-Z_][A-Z0-9_]{3,}\s*=\s*\S
\b(GET|POST|PUT|PATCH|DELETE)\s+/[A-Za-z0-9_/{-]
\byii/src/(handlers|queries|validators|transformers|factories|dto|adapters|clients|enums|exceptions|alerts|events|models|controllers|commands|views)/[A-Za-z0-9_/]+\.php\b
```

### R10 fixed unfinished-content checks

- In `draft`, unfinished markers are allowed and do not create an R10 finding.
- In `in-review` and `accepted`, flag every `<[^>\n]+>` token after the
  preprocessing above and every exact standalone uppercase marker `TODO`,
  `TBD`, `FIXME`, or `OVERWEEG`.
- Lines 1 through the first exact `---` separator are always scanned for those
  tokens, including tokens inside inline code. Body inline-code and fenced
  examples remain excluded.
- In `accepted`, §10 must be absent or contain no content other than exactly
  `Geen.` or `- Geen.` after comments and fences are removed. Any other non-empty
  line below the §10 heading is one R10 finding at that line.
- In template mode, the literal template placeholders and draft status are
  allowed. No other target receives template-mode exceptions.

## Findings and outcome

Every rule finding uses exactly:

```text
[R#] path:line — concrete reason
```

If target resolution, reading, or preprocessing prevents reliable rule
evaluation, report exactly `[PROCESS] path:line — concrete reason`, skip the
affected document's remaining rules, and set the outcome to `FAIL`. A processing
failure is not an extra PRD rule.

Do not merge different rule failures. After all findings, emit this block once
per target in lexicographic path order:

```text
Document: {path}
Status: {draft | in-review | accepted | template | missing}
Validator revision: AIMM-PRD-1
Document revision: {value or missing}
Outcome: PASS | FAIL
Next step: {status-dependent action}
```

Then emit one aggregate result:

```text
Documents: {count}
Overall outcome: PASS | FAIL
```

Use `FAIL` for any finding or processing problem. Identical repository input
must yield the same ordered findings. A clean result says that the mechanical
contract passes, not that the feature content is semantically correct.

Use these status-dependent next steps for a passing document:

- `draft`: fill remaining author decisions before moving to `in-review`.
- `in-review`: continue with semantic `/review-spec` when available.
- `accepted`: planning or implementation is permitted, subject to normal AIMM
  workflow gates.
- template: the canonical contract shape passes.

End a failed result with this choice line as its last non-empty line:

```text
Spec herstellen / Opnieuw valideren / Stoppen?
```

End a passing spec result with:

```text
Review starten / Opnieuw valideren / Stoppen?
```

For a passing template, replace `Review starten` with `Specs valideren`.
