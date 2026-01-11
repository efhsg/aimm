---
name: squash-migrations
description: Squash all database migrations into a schema migration plus a seed migration. Use when migration count becomes unwieldy or for clean deployment setup.
area: database
---

# SquashMigrations

Consolidate all database migrations into two files: one for structure, one for seed data.

## When to Use

- Migration count has grown large (10+ migrations)
- Setting up a new environment and want clean migrations
- Before major releases to simplify deployment history
- After stabilizing schema changes from a development cycle

## Output

Two migrations:
1. **Schema migration** (`m{date}_000001_squashed_schema.php`) - Database structure only
2. **Seed migration** (`m{date}_000002_initial_seed.php`) - Reference data for `data_source` table

## Inputs

- Docker containers: `aimm_yii` (app), `aimm_mysql` (database)
- Migration path: `yii/migrations/`
- Database credentials: `aimm` / `aimm_secret` / `aimm`

## Safety Invariants

- **Never run on production** without a full database backup
- Schema structure must be identical before and after squash
- Original migrations are archived first, then deleted after verification
- Process is reversible: restore from git if needed

## Algorithm

### 1. Backup Current State

Dump schema structure (no data) via MySQL container:

```bash
docker exec aimm_mysql mysqldump -u aimm -paimm_secret --no-data --skip-comments aimm 2>/dev/null > /tmp/schema_before.sql
```

Backup seed data:

```bash
docker exec aimm_mysql mysqldump -u aimm -paimm_secret --no-create-info --skip-comments aimm data_source 2>/dev/null > /tmp/seed_data.sql
```

### 2. Run Squash Command

Generate both migrations with a single command (use local `php yii`, not `vendor/bin/yii`):

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

Options:
- `--archive` (`-a`): Move old migrations to `archived/` directory
- `--with-seed` (`-s`): Auto-generate seed migration from `data_source` table
- `--seed-tables`: Customize which tables to seed (default: `data_source`)

This creates:
1. Schema migration: `m{timestamp}_squashed_schema.php`
2. Seed migration: `m{timestamp}_initial_seed.php` (1 second later)

### 3. Reset Database

Apply both migrations:

```bash
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

### 4. Verify

Compare schemas:

```bash
diff /tmp/schema_before.sql /tmp/schema_after.sql
```

**Acceptable differences:**
- AUTO_INCREMENT values
- Index ordering within CREATE TABLE
- `tinyint(1)` vs `tinyint`
- `ON UPDATE CURRENT_TIMESTAMP`

**Critical differences (require rollback):**
- Missing tables or columns
- Changed column types
- Missing foreign keys

Verify seed data:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SELECT COUNT(*) FROM data_source" 2>/dev/null
```

### 5. Cleanup

On success, remove archived migrations:

```bash
rm -rf yii/migrations/archived
```

On failure, rollback:

```bash
mv yii/migrations/archived/m*.php yii/migrations/
rm yii/migrations/m*_squashed_schema.php yii/migrations/m*_initial_seed.php
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

## Known Limitations

1. **`ON UPDATE CURRENT_TIMESTAMP`**: Not detected from schema, columns lose this behavior
2. **Seed data scope**: Only `data_source` is seeded; other reference tables (sector, industry, collection_policy) are populated via application seeders

## Definition of Done

- [ ] Schema backed up before squash
- [ ] Squash command with `--archive --with-seed` completed without errors
- [ ] Schema migration generated
- [ ] Seed migration auto-generated with `data_source` entries
- [ ] Database reset with both migrations
- [ ] Table list matches before/after
- [ ] No critical schema differences
- [ ] Seed data verified (data_source count matches)
- [ ] Archived migrations removed
- [ ] Result reported: SUCCESS or ROLLBACK
