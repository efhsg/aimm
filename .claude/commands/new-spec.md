---
allowed-tools: Read, Write, Glob, Grep, Bash(grep:*), AskUserQuestion, Bash(git rev-parse --show-toplevel), Bash(realpath:*), Bash(test:*), Bash(mkdir:*)
description: Create one AIMM-PRD-1 spec from a feature name or Markdown source
label: Create AIMM Spec
min_level: standard
argument-hint: '{name} [--from {path}]'
---

# New Spec

Load and follow `.claude/skills/new-spec.md`.

## Task

Create a new AIMM feature spec for: $ARGUMENTS

## Usage

- `/new-spec my-feature` — create a draft from the canonical template.
- `/new-spec my-feature --from path/to/source.md` — map one Markdown source and
  add a gap report.

The skill owns the non-overwrite guarantee and the gap report. After authors
fill the visible gaps, continue with `/validate-spec` and then semantic review.
