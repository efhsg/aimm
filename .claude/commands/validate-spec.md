---
allowed-tools: Read, Glob, Grep, Bash(git rev-parse --show-toplevel), Bash(realpath:*), Bash(test:*)
description: Validate AIMM specs mechanically against the AIMM-PRD-1 R1-R12 contract
label: Validate AIMM Spec
min_level: standard
argument-hint: '{path} | --all'
---

# Validate Spec

Load and follow `.claude/skills/validate-spec.md`.

## Task

Validate AIMM spec document(s): $ARGUMENTS

## Usage

- `/validate-spec .ai/features/my-feature/spec.md` — validate one spec.
- `/validate-spec .claude/templates/spec-prd.md` — validate the canonical template.
- `/validate-spec --all` — validate every `.ai/features/*/spec.md` plus the template.

This command is read-only. Report `PASS` only when every applicable R1–R12 check
passes; otherwise report stable findings and `FAIL`. Semantic review is a
separate follow-up.
