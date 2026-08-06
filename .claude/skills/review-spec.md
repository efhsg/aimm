---
name: review-spec
description: Read-only semantic review of one mechanically valid AIMM spec, used after validate-spec to report up to three prioritized findings per present section without an approval verdict
area: validation
provides:
  - aimm_spec_semantic_review
depends_on:
  - templates/spec-prd.md
  - skills/validate-spec.md
---

# Review Spec

Review what an AIMM functional spec means after its mechanical shape passes.
Advise the author; never edit the spec, change its status, stage files, or issue
an approval, readiness, pass, or fail verdict.

## Input and precondition

Accept exactly one readable, non-escaping direct path matching
`.ai/features/{name}/spec.md`. Reject missing, multiple, template, plan, or
out-of-root targets.

Read the spec completely and apply the current `.claude/skills/validate-spec.md`
R1–R12 contract to the same file first. If any rule or processing finding exists:

- do not start semantic review;
- reproduce the concrete validation findings;
- refer the author to `/validate-spec`;
- end with `Spec herstellen / Opnieuw valideren / Stoppen?` as the final line.

Do not rely on a prior conversational claim that validation passed; evaluate the
current file. A clean mechanical result permits review but is not an approval.

## Review method

1. Detect output language from the §1 core sentence; use Dutch for a Dutch spec
   and English for an English spec.
2. Read all present numbered sections in order and compare them with each other.
3. Record only material ambiguity, contradiction, missing evidence, or
   untestable behavior. Do not manufacture a comment for every checklist item.
4. Assign each finding to the section where the author should resolve it.
5. Rank by urgency (`hoog`, `middel`, `laag`), then source line. Keep only the
   first three findings per present section.
6. For every present section, emit its findings or the explicit no-finding line.
   Skip an absent optional §8–§10 without comment.

Urgency means:

- `hoog` — unresolved product, provenance, data-integrity, or contradiction risk
  that should be decided before acceptance or implementation;
- `middel` — material clarity or testability weakness worth correcting;
- `laag` — localized wording, organization, or polish.

## Section checklist

### §1 Doel en context

- Does the core sentence say what changes, for whom, and why?
- Is the problem concrete without prescribing implementation?
- Are financial claims framed with the evidence they require?

### §2 Actoren

- Does each role describe an action and ownership or access boundary?
- Are data-source owners, maintainers, reviewers, or automated actors missing?
- Is responsibility for provenance or exception handling clear where relevant?

### §3 Scope

- Are in-scope and out-of-scope items substantial and similarly granular?
- Does every exclusion explain why it is excluded?
- Are source selection, financial periods/units, missing values, and existing
  data behavior bounded where they affect the feature?

### §4 Happy path

- Are steps sequential, actor-led, externally observable, and implementation-free?
- Does the flow say where relevant inputs and provenance originate?
- Are period, unit/currency, transformation, validation, and deterministic output
  visible where the flow depends on them?

### §5 Edge cases

- Cover applicable missing, stale, partial, invalid, or conflicting data.
- Cover unavailable sources, timeout/rate limit, retry, repeated runs, and
  concurrency where relevant.
- Cover mismatched period, unit/currency, transformation, or validation state.
- Require explicit behavior rather than `unknown` or an unowned decision.

### §6 Acceptatiecriteria

- Is every outcome observable and non-duplicative?
- Do criteria cover the happy path and critical edge cases?
- Can provenance, missing-data, period/unit, and determinism requirements be
  verified where relevant?
- Do prefixes match the feature slug?

### §7 Impact op bestaande flows en data

- Does `Raakt` name concrete AIMM flows, entities, reports, or datasets?
- Are dependency directions and ordering explicit?
- Are existing records, historical outputs, backfills, and user-visible changes
  addressed or explicitly not applicable?
- Are host/runner boundaries stated when validation or operation depends on them?

For optional §8–§10, match the checklist by the actual section title, not by
its number. Apply the named checks below only when that title describes the
named concept. For any differently titled optional section, review its actual
rules, claims, or tables for internal consistency, observable behavior, clear
ownership, and compatibility with the rest of the spec.

### Validatie en randvoorwaarden

- Are preconditions, invariants, and postconditions observable and consistent?

### Toestandsmachine

- Are states, actors, transitions, and forbidden transitions complete?

### Open punten

- Does each point name an owner or required source and a concrete next step?
- Would any point contradict the current status or authorize premature work?

## Output contract

Start the report once with `# Semantische review: {path}`. Then use this block
for every present section that has findings:

```markdown
## §N {section title}

### Bevinding {1..3}
- **Urgentie:** {hoog | middel | laag}
- **Bewijs:** `{path}:{line}` — {short exact passage or precise observation}
- **Waarom:** {risk or ambiguity}
- **Verbeterrichting:** {bounded author action without choosing the product answer}
```

When a present section has no material finding, emit its heading followed by:

```text
§N — geen materiële bevindingen
```

After all present sections, add `## Algemene observatie` with one short,
descriptive paragraph about strengths or recurring patterns. Do not use words
such as `goedgekeurd`, `afgekeurd`, `klaar`, `pass`, `fail`, or `ready` as a
summary verdict.

With findings, end with this choice line as the last non-empty line:

```text
Bevindingen bespreken / Spec aanpassen / Stoppen?
```

Without material findings, end with:

```text
Review bespreken / Stoppen?
```

## Completion

- The current file passed R1–R12 before semantic review.
- Every present section has at most three urgency-ordered findings or an explicit
  no-finding line.
- Every finding contains evidence, reason, and a bounded improvement direction.
- Relevant provenance, source conflict, period, unit, transformation,
  missing-data behavior, and determinism were considered.
- The spec and all repository, Git, database, and external state are unchanged.
- No automatic approval or status verdict was issued.
