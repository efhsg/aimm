---
allowed-tools: Read, Glob, Grep, Bash(grep:*), Bash(git status:*), Bash(git rev-parse:*), Bash(git log:*), Bash(git diff:*), Bash(ls:*), Bash(test:*)
description: Plan an exceptional migration squash behind explicit safety approvals
label: Squash AIMM Migrations
min_level: heavy
argument-hint: '[environment and approval context]'
---

# Squash Migrations

Load and follow `.claude/skills/squash-migrations.md`.

This command is a thin router. The skill owns the approval gates, environment
boundary, generation command, validation, recovery, and reporting contract.
Do not execute any database or migration mutation until every skill gate that
precedes it has explicit approval.

## Task

$ARGUMENTS
