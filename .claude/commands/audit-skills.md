---
allowed-tools: Read, Glob, Grep, Bash(grep:*), Bash(git rev-parse --show-toplevel), Bash(git status --short), Bash(realpath:*), Bash(test:*)
description: Audit the registered AIMM skill ecosystem for drift, conflicts, overlap, and unsafe workflow contracts without changing files
label: Audit Skills
min_level: heavy
argument-hint: '[baseline=<report-path>]'
---

# Audit Skills

Load and follow `.claude/skills/audit-skills.md`.

## Task

Run the read-only AIMM skill-ecosystem audit: $ARGUMENTS

`baseline=<report-path>` is optional and may only identify a previously saved
audit report for comparison. This command has no fix or apply mode.
