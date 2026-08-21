---
allowed-tools: Read, Glob, Grep, AskUserQuestion, Bash(git rev-parse:*), Bash(git branch --show-current), Bash(git status:*), Bash(git diff:*), Bash(git add --:*), Bash(jq empty:*)
description: Validate an approved AIMM change scope, handle host handoff, stage exact paths, and suggest a commit without committing or pushing
label: Finalize Changes
min_level: heavy
argument-hint: '[approved paths] [feature=<slug-or-path>]'
---

# Finalize Changes

Load and follow `.claude/skills/finalize-changes.md`.

## Task

Finalize the approved AIMM changes: $ARGUMENTS
