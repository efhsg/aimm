---
name: custom-buttons
description: Contract for closing an AIMM turn at every stop point with a detectable choice line and an explicit wait instruction, offering bounded non-destructive next actions
area: workflow
provides:
  - choice_button_syntax
depends_on: []
---

# Custom Buttons

Close every stop point in an AIMM command or skill with one choice line as the
last non-empty line of the turn. A runner that supports it renders the line as
clickable buttons; a runtime without that renderer keeps the same line as a
readable stop prompt. The contract is therefore about the written prompt, not
about a specific product feature.

## Invariant

A choice line presents options only. Selecting an option never authorizes a
mutation that its own gate has not already approved, and never resumes a
workflow that stopped because a check failed. Offer at most one choice line per
turn, and never place text after it.

## Identification protocol

A prompt needs a choice line wherever the text contains one of these patterns.
Scan for all of them before concluding that a prompt is complete:

| Pattern | Example wording |
|---------|-----------------|
| Explicit stop | `stop`, `Stop if`, `End after presenting the report` |
| Wait for user | `wait for`, `do not proceed`, `requires explicit approval` |
| Approval gate | `approval gate`, `separate explicit owner decision` |
| Phase transition | `continue with`, `next phase`, `downstream handoff` |
| Blocked check | `maintainer handoff required`, `blocking`, `fail closed` |
| Terminal report | `Output`, `Completion`, `Definition of Done` |

## Preferred syntax

```text
Optie1 / Optie2 / Stoppen?
```

| Rule | Detail |
|------|--------|
| Separator | ` / ` — space, slash, space |
| Option count | 2 or 3 in AIMM; 5 is the detection maximum |
| Option length | 80 characters maximum |
| Context prefix | Text before `—` or `–` is stripped from the labels |
| Parentheses | Optional: `Vraag? (A / B / C)` |
| Trailing `?` | Optional; AIMM always writes it |

## AIMM label convention

- Write labels in Dutch, imperative, and concrete. The instruction body of the
  skill stays English; only the rendered turn is Dutch.
- Name the action, not the agreement: `Bevindingen bespreken`, not `Akkoord`.
- The last option is always `Stoppen?`. Every registered AIMM capability that
  offers choices follows this, so a user always has one identical exit.
- Keep the label short enough to read as a button; move context in front of an
  em dash when it is needed.

## Wait semantics and authorization

Two separate requirements, judged on meaning rather than on formatting. Both
belong in the skill or command file, never in the rendered turn.

### Wait semantics

Required when the file has steps after the choice line, because the agent can
otherwise answer its own question and continue inside the same turn. Optional
when the choice line ends the workflow: ending the turn is the wait.

Any of these forms satisfies it:

```markdown
..., then wait:
```

```markdown
**Wait for user input. Do not proceed until the user answers.**
```

```markdown
Then wait; do not turn the selected option into a file mutation.
```

### Option-scoped authorization

Required as soon as one option starts a mutation, or an action that belongs to
another capability. Name the exact command or action the label authorizes, and
nothing beyond it:

> Only `Publiceren` authorizes `git push -u <remote> <branch-name>`.

> `Committen` starts `/cp` as a separate approved action; this skill never
> commits.

This is stronger than a wait instruction. Waiting stops the agent before the
answer; option-scoped authorization also bounds what the answer permits. A
read-only capability whose options name a follow-up must state that the
follow-up runs outside the current workflow.

### Documented autonomous mode

A capability may skip a gate only when it names that mode explicitly, as
`review-changes.md` does for its plan gate. Silence is not a mode.

## Bracket-letter alternative

Use bracket-letters only when labels exceed roughly 40 characters or need a
letter code. The bracket lines must be the last consecutive non-empty lines.

```text
[H] Herstelscope bepalen
[V] Skill verdiepen
[S] Stoppen
```

Do not mix both syntaxes in one turn.

## Context to choice mapping

| Context | Choice line |
|---------|-------------|
| Read-only audit, findings present | `Bevindingen bespreken / Herstelscope bepalen / Stoppen?` |
| Read-only audit, no findings | `Opnieuw uitvoeren / Stoppen?` |
| Mechanical validation failed | `Spec herstellen / Opnieuw valideren / Stoppen?` |
| Mechanical validation passed | `Review starten / Opnieuw valideren / Stoppen?` |
| Semantic review with findings | `Bevindingen bespreken / Spec aanpassen / Stoppen?` |
| Required host evidence missing | `Bewijs aanleveren / Scope aanpassen / Stoppen?` |
| Ready to finalize | `Committen / Diff bekijken / Stoppen?` |
| Network action pending | `Publiceren / Lokaal houden / Stoppen?` |
| Approval gate before a mutation | `{exacte actie} uitvoeren / Plan aanpassen / Stoppen?` |
| Ambiguous scope before work starts | `Scopebesluit maken / Scope aanpassen / Stoppen?` |

When a gate guards a mutation, put the exact target in the label or in the
context prefix, so the user approves a bounded action rather than a category.

## Safety rules

- Never offer an option that bypasses a failed check, skips required validation,
  or converts a `maintainer handoff required` result into a pass.
- Never use a destructive command as a label. `migrate/fresh`, `db/reset`,
  `reset --hard`, and force-push belong in a written plan behind their own
  approvals, not in a button.
- Never combine two mutations in one option. One gate approves one action.
- Never offer an option whose tools the command's `allowed-tools` does not grant.

## Anti-patterns

**Wrong — text after the choice line:**

```text
Committen / Diff bekijken / Stoppen?
Laat me weten wat je wilt.
```

**Right — choice line last:**

```text
Laat me weten wat je wilt.

Committen / Diff bekijken / Stoppen?
```

Also avoid: six or more options, vague labels such as `Ga verder` or `OK`,
mixed slash and bracket syntax in one turn, and a mutating option that names no
bounded action.

Do not add a wait instruction after every choice line by reflex. Where two
choice lines are the alternative endings of one step, one shared statement after
both is correct; inserting a line between them breaks the branch structure.

## Validation checklist

Every stop point must satisfy all six:

- [ ] The choice line is the last non-empty line of the turn.
- [ ] Separator is ` / ` or the bracket format is used correctly.
- [ ] Two or three options, with `Stoppen?` last.
- [ ] Every option is a concrete, bounded, non-destructive next action.
- [ ] Wait semantics are present when the file continues after the choice line.
- [ ] Option-scoped authorization is present when an option starts a mutation or
      an action owned by another capability.

## Definition of Done

- Every pattern found by the identification protocol has a choice line.
- Labels follow the Dutch imperative convention and end with `Stoppen?`.
- Wait semantics exist wherever the file continues after the choice line.
- Every mutating or cross-capability option names the exact action it authorizes.
- No option bypasses a gate, a failed check, or a permission boundary.
- The skill is registered in `.claude/skills/index.md`.
