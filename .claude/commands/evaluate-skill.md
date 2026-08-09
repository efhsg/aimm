---
allowed-tools: Read, Glob, Grep, Bash(grep:*)
description: Evaluate one AIMM skill or external candidate semantically without changing files
label: Evaluate AIMM Skill
min_level: standard
argument-hint: '{name-or-path} [transfer]'
---

# Evaluate Skill

Load and follow `.claude/skills/evaluate-skill.md`.

## Task

Evaluate this skill against its stated goal: $ARGUMENTS

Use standard mode for an AIMM skill and `transfer` for an explicitly selected
external candidate. The evaluation is read-only and never applies findings.
