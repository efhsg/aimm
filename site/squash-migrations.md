# Squash Migrations

Consolidate all database migrations into two files: one for schema structure, one for seed data.

## When to Use

- Migration count has grown large (10+ migrations)
- Setting up a new environment and want clean migrations
- Before major releases to simplify deployment history
- After stabilizing schema changes from a development cycle

## Quick Start

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

## Output

Two migrations are generated:

1. **Schema migration** (`m{date}_squashed_schema.php`) - Database structure only (tables, indexes, foreign keys)
2. **Seed migration** (`m{date}_initial_seed.php`) - Reference data for `data_source` table

## Command Options

```bash
docker exec aimm_yii php yii squash-migrations [options]
```

| Option | Alias | Description |
|--------|-------|-------------|
| `--archive` | `-a` | Move old migrations to `archived/` directory |
| `--with-seed` | `-s` | Auto-generate seed migration from reference tables |
| `--seed-tables` | | Comma-separated list of tables to seed (default: `data_source`) |

## Seeding Multiple Tables

To include additional reference data in the seed migration:

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed --seed-tables=data_source,sector,industry,collection_policy,company
```

**Table order matters** - list tables in FK dependency order (parent tables first):

| Table | Dependencies |
|-------|--------------|
| `data_source` | none |
| `sector` | none |
| `industry` | `sector` |
| `collection_policy` | `industry` |
| `company` | `industry` |

## Full Process

### 1. Backup Current State

The squash command reads from the current database schema. Ensure your database is up-to-date:

```bash
docker exec aimm_yii php yii migrate --interactive=0
```

### 2. Run Squash Command

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

This will:
- Generate a schema migration from the current database structure
- Generate a seed migration with `data_source` entries
- Move existing migrations to `yii/migrations/archived/`

### 3. Reset Database

Apply the new migrations:

```bash
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

> [!WARNING]
> Use `migrate/fresh`, not `db/reset`. The `db/reset` command tries to revert archived migrations which no longer exist in the migrations folder.

### 4. Verify

Compare table counts:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SHOW TABLES" 2>/dev/null | wc -l
```

Verify seed data:

```bash
docker exec aimm_mysql mysql -u aimm -paimm_secret aimm -N -e "SELECT COUNT(*) FROM data_source" 2>/dev/null
```

### 5. Cleanup

On success, remove the archived migrations:

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

1. **`ON UPDATE CURRENT_TIMESTAMP`** - Not detected from schema introspection; columns lose this behavior after squash
2. **Excluded columns** - `created_at` and `updated_at` are excluded from seed data (auto-populated by application)

## Troubleshooting

### "Seed table not found" warning

The specified table doesn't exist in the database. Check the table name spelling and ensure the database is migrated.

### Foreign key constraint errors on migrate/fresh

Tables in `--seed-tables` are inserted in the order specified. Ensure parent tables come before child tables.

### Schema differences after squash

Minor differences are acceptable:
- `AUTO_INCREMENT` values
- Index ordering within `CREATE TABLE`
- `tinyint(1)` vs `tinyint`

Critical differences requiring rollback:
- Missing tables or columns
- Changed column types
- Missing foreign keys
