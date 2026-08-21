---
allowed-tools: Read, Glob, Grep, Bash(grep:*), Bash(git rev-parse --show-toplevel), Bash(realpath:*), Bash(test:*)
description: Review one mechanically valid AIMM spec section by section without changing it or issuing an approval verdict
label: Review Spec
min_level: standard
argument-hint: '{path}'
---

# Review Spec

Load and follow `.claude/skills/review-spec.md`.

## Task

Semantically review this AIMM spec: $ARGUMENTS

Accept exactly one `.ai/features/{name}/spec.md` path, and apply `/validate-spec`
to the same file first. The skill owns the read-only boundary and the rule that
this review issues no approval verdict.
