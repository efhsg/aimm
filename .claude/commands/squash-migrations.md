---
allowed-tools: Bash, Read, Write, Glob
description: Squash migrations with backup, verification, and automatic rollback
---

# Squash Migrations

Safely squash all database migrations into a single schema file plus a seed file, with automatic rollback on failure.

## Output

Two migrations:
1. `m{timestamp}_squashed_schema.php` - Database structure only (tables, indexes, FKs)
2. `m{timestamp}_initial_seed.php` - Reference data required to run the app

## Steps

### 1. Pre-flight checks

Verify migrations exist:

```bash
ls -la yii/migrations/m*.php | head -20
```

Check current migration count:

```bash
ls yii/migrations/m*.php 2>/dev/null | wc -l
```

If only 1 migration exists, abort with message: "Only 1 migration found. Nothing to squash."

### 2. Backup schema and seed data

Create backup directory and store path:

```bash
BACKUP_DIR="/tmp/squash-backup-$(date +%Y%m%d_%H%M%S)" && mkdir -p "$BACKUP_DIR" && echo "$BACKUP_DIR"
```

Dump schema via MySQL container (structure only, no data):

```bash
docker exec aimm_mysql mysqldump -u aimm -paimm_secret --no-data --skip-comments aimm 2>/dev/null | grep -v "^--" | grep -v "^/\*" | grep -v "^$" > $BACKUP_DIR/schema_before.sql
```

Get table list for verification:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SHOW TABLES" 2>/dev/null > $BACKUP_DIR/tables_before.txt
```

Backup seed data from reference tables:

```bash
docker exec aimm_mysql mysqldump -u aimm -paimm_secret --no-create-info --skip-comments aimm data_source 2>/dev/null > $BACKUP_DIR/seed_data.sql
```

Report: "Backed up schema with N tables"

### 3. Run squash command with seed generation

Execute the squash with archive and with-seed flags (use local `php yii`, not `vendor/bin/yii`):

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

This generates two migrations:
1. Schema migration: `m{timestamp}_squashed_schema.php`
2. Seed migration: `m{timestamp}_initial_seed.php` (1 second later timestamp)

Options:
- `--archive` (`-a`): Move old migrations to `archived/` directory
- `--with-seed` (`-s`): Auto-generate seed migration from `data_source` table
- `--seed-tables`: Customize which tables to seed (default: `data_source`)

If command fails, report error and stop.

### 4. Reset database

Use `migrate/fresh` to drop all tables and re-apply (NOT `db/reset` which tries to revert archived migrations):

```bash
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

If reset fails, proceed to rollback.

### 5. Verify schema integrity

Dump new schema:

```bash
docker exec aimm_mysql mysqldump -u aimm -paimm_secret --no-data --skip-comments aimm 2>/dev/null | grep -v "^--" | grep -v "^/\*" | grep -v "^$" > $BACKUP_DIR/schema_after.sql
```

Get new table list:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SHOW TABLES" 2>/dev/null > $BACKUP_DIR/tables_after.txt
```

Compare table lists:

```bash
diff $BACKUP_DIR/tables_before.txt $BACKUP_DIR/tables_after.txt
```

If tables differ, proceed to rollback.

Compare schemas (allow minor formatting differences):

```bash
diff $BACKUP_DIR/schema_before.sql $BACKUP_DIR/schema_after.sql
```

Note: Some differences are acceptable:
- AUTO_INCREMENT values
- Index ordering within CREATE TABLE
- `tinyint(1)` vs `tinyint`
- `ON UPDATE CURRENT_TIMESTAMP` (not captured by schema reader)

Critical differences requiring rollback: missing tables or columns.

Verify seed data:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SELECT COUNT(*) FROM data_source" 2>/dev/null
```

### 6. Handle result

#### On SUCCESS (schemas match or only minor differences):

Remove archived migrations directory:

```bash
rm -rf yii/migrations/archived
```

Report:
```
SQUASH SUCCESSFUL

- Migrations squashed: [count] -> 2
- Schema migration: [filename]
- Seed migration: [filename]
- Tables verified: [count]
- Seed records: [count] data_source entries

The squashed migrations are ready. Original migrations were deleted (recoverable from git).
```

#### On FAILURE (critical schema differences):

Execute rollback:

```bash
# Restore archived migrations
mv yii/migrations/archived/m*.php yii/migrations/

# Remove failed squash and seed migrations
rm yii/migrations/m*_squashed_schema.php
rm yii/migrations/m*_initial_seed.php

# Reset database with original migrations
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

Report:
```
SQUASH ROLLED BACK

Schema verification failed. Differences detected:
[diff output]

Original migrations restored. Database reset to previous state.
Please review the squash-migrations command output for errors.
```

### 7. Cleanup

Remove temporary backup files:

```bash
rm -rf $BACKUP_DIR
```

## Task

$ARGUMENTS
