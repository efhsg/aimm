---
allowed-tools: Read, Glob, Grep, Bash(grep:*), AskUserQuestion, Bash(git rev-parse:*), Bash(git status:*), Bash(git diff:*), Bash(git ls-files:*), Bash(git show:*), Bash(git log:*), Bash(git branch:*), Bash(realpath:*), Bash(readlink:*), Bash(test:*), Bash(jq empty:*)
description: Review current AIMM changes for evidenced defects, financial integrity, project compliance, and missing verification
label: Review Changes
min_level: heavy
argument-hint: '[scope] [staged] [interactive]'
---

# Review Changes

Load and follow `.claude/skills/review-changes.md`.

## Task

Review the current AIMM changes: $ARGUMENTS

The review is read-only. Do not repair findings unless the requester starts a
separate implementation action.
