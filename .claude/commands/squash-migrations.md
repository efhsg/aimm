---
allowed-tools: Bash, Read, Write, Glob
description: Plan an exceptional migration squash behind explicit safety approvals
---

# Squash Migrations

Follow the skill contract in `.claude/skills/squash-migrations.md`.

This command is a thin router. The skill owns the approval gates, environment
boundary, generation command, validation, recovery, and reporting contract.
Do not execute any database or migration mutation until every skill gate that
precedes it has explicit approval.

## Task

$ARGUMENTS
