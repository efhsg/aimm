---
allowed-tools: Read, Glob, Grep, Bash(git rev-parse --show-toplevel), Bash(realpath:*), Bash(test:*)
description: Review one mechanically valid AIMM spec section by section without changing it or issuing an approval verdict
label: Review AIMM Spec
min_level: standard
argument-hint: '{path}'
---

# Review Spec

Load and follow `.claude/skills/review-spec.md`.

## Task

Semantically review this AIMM spec: $ARGUMENTS

Accept exactly one `.ai/features/{name}/spec.md` path. Apply `/validate-spec` to
the same file first. The review is read-only, on-demand, and advisory: never
change status or content and never issue a pass/fail or approval verdict.
