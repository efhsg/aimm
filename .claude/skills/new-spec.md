---
name: new-spec
description: Generate one non-overwriting AIMM-PRD-1 spec from a feature name or one Markdown source
area: workflow
provides:
  - aimm_prd_spec_generation
depends_on:
  - rules/workflow.md
  - templates/spec-prd.md
---

# New Spec

Create exactly one new functional spec under `.ai/features/{name}/`. Protect the
contract shape and provenance; do not invent product decisions or financial
facts.

## Inputs

- `name` — required feature-map name matching `^[a-z][a-z0-9-]{2,30}$`.
- `--from {path}` — optional, exactly one readable Markdown source.

Reject unknown arguments, multiple sources, a source outside the active AIMM
worktree, or a non-Markdown source.

## Contract

- Canonical template: `.claude/templates/spec-prd.md`.
- Supported revision: `AIMM-PRD-1`.
- Output spec: `.ai/features/{name}/spec.md`.
- Source-mode gap report: `.ai/features/{name}/spec.gaps.md`.
- New specs start as `draft`; only `accepted` authorizes implementation.
- Generation never edits an existing spec.

Before writing, read the template and `.claude/skills/validate-spec.md`. Stop if
either is missing, empty, or does not support `AIMM-PRD-1`; report the revisions
found rather than presenting output as valid.

## Procedure

1. Resolve the active worktree and parse the arguments.
2. Validate `name`, source path, and source count.
3. Read all `.ai/features/*/spec.md` metadata. Set the proposed feature slug to
   `name` when it satisfies `^[a-z][a-z0-9-]{2,23}$`; otherwise ask for a valid
   slug. Require uniqueness before continuing.
4. Refuse to write when `.ai/features/{name}/spec.md` exists. An existing map
   without `spec.md` is allowed.
5. In source mode, read the complete source and determine whether it describes
   more than one independent candidate feature. If so, ask which single feature
   is in scope and write nothing until the author chooses.
6. Build the document from the canonical template. Fill feature name, map,
   slug, current date, `draft`, and `AIMM-PRD-1`.
7. Recheck target absence and slug uniqueness immediately before writing.
8. Write `spec.md`. In source mode, also write `spec.gaps.md` using the format
   below. Do not create other files.
9. Report paths, mode, slug, contract revision, and remaining required gaps.

If any read, validation, mapping, or write step is uncertain or partial, stop
fail-closed. Never describe a partially generated document as complete.

## Source mapping

Recognize H2 and H3 headings case-insensitively. Match complete heading meaning,
allowing numbering and trailing explanation, against this table:

| Source meaning | Target |
|----------------|--------|
| doel, context, goal, purpose | §1 Doel en context |
| actoren, rollen, actors, roles, stakeholders | §2 Actoren |
| scope, in scope, niet in scope, out of scope | §3 Scope |
| flow, happy path, workflow, stappen, steps | §4 Happy path |
| edge cases, randgevallen, exceptions | §5 Edge cases |
| acceptatiecriteria, acceptance criteria, AC | §6 Acceptatiecriteria |
| impact, afhankelijkheden, dependencies | §7 Impact op bestaande flows en data |

Apply these rules deterministically:

- Copy the matched body literally; do not summarize, repair, or reinterpret it.
- Preserve nested H3 content inside a matched H2. Treat an H3 as a new mapping
  boundary only when its own heading matches this table.
- The first match supplies the primary body. Append later matches for the same
  target as separate continuation blocks in source order.
- Emit target sections in template order regardless of source order.
- Replace the body of every unmapped required section with exactly
  `> TODO — {concrete reason}`. For a mapped but mechanically incomplete
  section, append the same marker with the missing requirement as its reason.
- List every unrecognized H2/H3 in the gap report; do not guess its target.
- Mapping does not imply that a section passes R1–R12. List missing table rows,
  steps, AC shape, impact fields, or other mechanical work as gaps.
- For a financial claim, require visible source attribution, reporting period,
  unit, transformation, and validation status where applicable. If any evidence
  is absent, keep the copied claim as unverified source text and record the
  missing evidence as an author gap; never elevate it to established truth.

Sections 8–10 are never inferred by this mapping. Keep them only when the source
has explicitly corresponding content and the placement is unambiguous;
otherwise remove them from the generated spec and list them as optional author
decisions in the gap report. When the author must decide before removal, use
exactly `> OVERWEEG — {concrete reason}`; `/validate-spec` treats this as an
unfinished marker outside `draft`.

## Gap report

Use this stable shape in source mode:

```markdown
# Spec Gap Report: {name}

**Bron:** `{path}`
**Gegenereerd:** {yyyy-mm-dd}
**Contractrevisie:** AIMM-PRD-1
**Spec:** `.ai/features/{name}/spec.md`

## Gemapt

- §{number} {target heading} — `{source heading}`

## Auteurswerk voor verplichte secties

- §{number} {target heading} — {missing content or mechanical requirement}

## Niet-herkende bronsecties

- `{source heading}`

## Optionele beslissingen

- §{number} {target heading} — {keep, fill, or remove}
```

Use `geen` for an empty list. Every required §1–§7 section appears either under
`Gemapt` or `Auteurswerk`; a mapped but incomplete section appears under both.

## Stop conditions

- Invalid or duplicate name/slug.
- Existing target spec.
- Missing or mismatched template/validator contract.
- Missing, unreadable, non-Markdown, or out-of-worktree source.
- More than one candidate feature without an explicit scope choice.
- Ambiguous mapping, processing error, or failed write/readback.

## Completion

Generation is complete only when the new files read back correctly and no
pre-existing file changed. Tell the author that visible gaps must be filled
before `in-review`, then run `/validate-spec`; semantic review remains separate.

End successful output with this choice line as its last non-empty line:

```text
Spec invullen / Valideren / Stoppen?
```

When generation stops before writing, end with:

```text
Invoer herstellen / Opnieuw uitvoeren / Stoppen?
```

Then wait. `Spec invullen` is author work and `Valideren` starts `/validate-spec`
as a separate action; generation never fills the visible gaps itself and never
overwrites an existing spec on a rerun.
