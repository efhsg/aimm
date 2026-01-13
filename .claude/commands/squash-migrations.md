---
allowed-tools: Bash, Read, Write, Glob
description: Squash migrations with backup, verification, and automatic rollback
---

# Squash Migrations

Follow the skill contract in `.claude/skills/squash-migrations.md`.

## Quick Reference

Output: Two migrations
1. `m{timestamp}_squashed_schema.php` - Database structure
2. `m{timestamp}_initial_seed.php` - Reference data (data_source)

Command: `docker exec aimm_yii php yii squash-migrations --archive --with-seed`

## Task

$ARGUMENTS
