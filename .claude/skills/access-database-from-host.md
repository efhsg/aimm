---
name: access-database-from-host
description: Access the `aimm` MySQL/MariaDB database from the host machine (no docker exec) using `.env` and the same database selection logic as `yii/config/db.php`. Use to inspect collection runs/errors safely without leaking credentials.
---

# AccessDatabaseFromHost

Access the AIMM database from the host machine in a way that is compatible with `.env` and `yii/config/db.php`, and that avoids leaking secrets in shell history/process lists.

## When to Use

- You need to query `aimm_*` tables from the host (agents are not allowed to `docker exec`).
- You need to inspect collection logs (`aimm_collection_run`, `aimm_collection_error`) to debug a failed collection.

## Inputs

From repo root `.env`:

- `DB_PORT` (published port on host)
- `DB_DATABASE` (default database)
- `DB_DATABASE_TEST` (used when `YII_ENV=test`, matching `yii/config/db.php`)
- `DB_USER`
- `DB_PASSWORD`

From `yii/config/db.php`:

- Database selection rule: if `YII_ENV=test` then use `DB_DATABASE_TEST`, else use `DB_DATABASE`.
- Table prefix: `aimm_` (so Yii table `{{%collection_run}}` is `aimm_collection_run`).

## Procedure (Host Only, No Secrets in argv)

Use a temporary MySQL defaults file so credentials do not appear in `ps` output:

```bash
set -euo pipefail

# From repo root or from `yii/` (adjust path to `.env` as needed)
set -a
source ../.env
set +a

db="${DB_DATABASE}"
if [ "${YII_ENV:-}" = "test" ]; then
  db="${DB_DATABASE_TEST}"
fi

tmp="$(mktemp)"
chmod 600 "$tmp"
cat >"$tmp" <<EOF
[client]
host=127.0.0.1
port=${DB_PORT}
user=${DB_USER}
password=${DB_PASSWORD}
database=${db}
EOF

mysql --defaults-extra-file="$tmp" --batch --raw -e "SELECT 1 AS ok;"
rm -f "$tmp"
```

### Notes

- Do not use `DB_HOST` from `.env` for host queries. `DB_HOST=aimm_mysql` is a Docker network hostname and typically does not resolve on the host.
- Do not pass passwords via `mysql -p...` in automation; it leaks via process arguments and may end up in logs.

## Common Queries

Latest collection runs for an industry:

```sql
SELECT
  id,
  industry_id,
  datapack_id,
  status,
  gate_passed,
  companies_total,
  companies_success,
  companies_failed,
  error_count,
  warning_count,
  started_at,
  completed_at,
  duration_seconds
FROM aimm_collection_run
WHERE industry_id = 'global-energy-supermajors'
ORDER BY started_at DESC
LIMIT 5;
```

Error summary for a run:

```sql
SELECT severity, error_code, COUNT(*) AS cnt
FROM aimm_collection_error
WHERE collection_run_id = 123
GROUP BY severity, error_code
ORDER BY severity, cnt DESC, error_code ASC;
```

Error details for a run:

```sql
SELECT severity, error_code, ticker, error_path, error_message, created_at
FROM aimm_collection_error
WHERE collection_run_id = 123
ORDER BY ticker ASC, severity ASC, error_code ASC, error_path ASC;
```

## Security / Guardrails

- Prefer a dedicated read-only DB user for agent queries (no writes) unless the task explicitly requires writes/migrations.
- Never paste `.env` contents into chat/logs; use variable names only.
- Avoid querying more than needed; always `LIMIT` and/or filter by `industry_id` / `collection_run_id`.

## Definition of Done

- Can run a host-only `mysql` query using `.env` without credential leakage in process args.
- Agent can retrieve latest runs and related `collection_error` rows for a specific industry/run id.
