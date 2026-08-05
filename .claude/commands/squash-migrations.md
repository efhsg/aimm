---
allowed-tools: Bash, Read, Write, Glob
description: Plan an exceptional migration squash behind explicit safety approvals
---

# Squash Migrations

Follow the skill contract in `.claude/skills/squash-migrations.md`.

This is not a routine development command. Do not execute a squash unless the
user explicitly requests it and separately approves:

- the exact environment, database, schema, migration directory, and generated files;
- a verified full backup and tested restore path;
- the production boundary and downtime/data-loss implications;
- the readback plan and the recovery action.

The PromptManager runner cannot execute this workflow. It may only produce a
maintainer plan and must not run local PHP, Docker, database reset, archive
cleanup, or migration deletion.

## Quick Reference

Expected output after approval: two migrations
1. `m{timestamp}_squashed_schema.php` - Database structure
2. `m{timestamp}_initial_seed.php` - Reference data (data_source)

Potential host command, shown only after all gates pass:

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

Stop after generation for readback. Never run `migrate/fresh`, delete archived
migrations, or restore a database automatically.

## Task

$ARGUMENTS
