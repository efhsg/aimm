---
name: access-database-from-host
description: Query the `aimm` database read-only from an AIMM host using maintainer-injected runtime variables and the selection logic from `yii/config/db.php`, without reading or exposing credentials.
---

# AccessDatabaseFromHost

Access the AIMM database from the host machine using the same database selection
logic as `yii/config/db.php`, without opening `.env` or exposing secrets in shell
history, process arguments, logs, or evidence.

## When to Use

- You need to query AIMM tables from an explicitly approved AIMM host.
- You need to inspect collection logs (`collection_run`, `collection_error`) to debug a failed collection.

Do not use this skill in the PromptManager runner. That environment has neither
an approved database credential channel nor authority to improvise one; provide
a maintainer handoff instead.

## Inputs

The maintainer injects these variables into the process environment without
showing their values to the agent:

- `DB_PORT` (published port on host)
- `DB_DATABASE` (default database)
- `DB_DATABASE_TEST` (used when `YII_ENV=test`, matching `yii/config/db.php`)
- `DB_READONLY_USER`
- `DB_READONLY_PASSWORD`

`DB_READONLY_USER` must be a dedicated account whose database grants permit
only the required reads. Generic application or administrative credentials are
not accepted by this skill.

From the read-only contract in `yii/config/db.php`:

- Database selection rule: if `YII_ENV=test` then use `DB_DATABASE_TEST`, else use `DB_DATABASE`.
- No table prefix is configured, so Yii table `{{%collection_run}}` resolves to
  `collection_run`.

## Procedure (Host Only, No Secrets in argv)

Use a temporary MySQL defaults file so credentials do not appear in `ps` output.
Do not print the file or its variables:

```bash
set -euo pipefail

: "${DB_PORT:?DB_PORT must be injected by the maintainer}"
: "${DB_DATABASE:?DB_DATABASE must be injected by the maintainer}"
: "${DB_READONLY_USER:?DB_READONLY_USER must be injected by the maintainer}"
: "${DB_READONLY_PASSWORD:?DB_READONLY_PASSWORD must be injected by the maintainer}"

db="${DB_DATABASE}"
if [ "${YII_ENV:-}" = "test" ]; then
  : "${DB_DATABASE_TEST:?DB_DATABASE_TEST must be injected for YII_ENV=test}"
  db="${DB_DATABASE_TEST}"
fi

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
chmod 600 "$tmp"
cat >"$tmp" <<EOF
[client]
host=127.0.0.1
port=${DB_PORT}
user=${DB_READONLY_USER}
password=${DB_READONLY_PASSWORD}
database=${db}
EOF

mysql --defaults-extra-file="$tmp" --batch --raw -e "SELECT 1 AS ok;"
```

### Notes

- Do not use `DB_HOST` from `.env` for host queries. `DB_HOST=aimm_mysql` is a Docker network hostname and typically does not resolve on the host.
- Do not open, source, copy, or print `.env`; runtime injection is the maintainer's responsibility.
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
FROM collection_run
WHERE industry_id = 'global-energy-supermajors'
ORDER BY started_at DESC
LIMIT 5;
```

Error summary for a run:

```sql
SELECT severity, error_code, COUNT(*) AS cnt
FROM collection_error
WHERE collection_run_id = 123
GROUP BY severity, error_code
ORDER BY severity, cnt DESC, error_code ASC;
```

Error details for a run:

```sql
SELECT severity, error_code, ticker, error_path, error_message, created_at
FROM collection_error
WHERE collection_run_id = 123
ORDER BY ticker ASC, severity ASC, error_code ASC, error_path ASC;
```

## Security / Guardrails

- Require the maintainer to verify the dedicated account's read-only grants
  before injecting it; stop if that verification is missing or stale.
- Never paste runtime variable values into chat or logs; use variable names only.
- Avoid querying more than needed; always `LIMIT` and/or filter by `industry_id` / `collection_run_id`.
- Never use this read-only skill as a mutation path.

## Definition of Done

- Can run a host-only `mysql` query through verified read-only credentials
  without credential leakage.
- Agent can retrieve latest runs and related `collection_error` rows for a specific industry/run id.
- Temporary defaults file is removed by the exit trap on success and failure.
- Query target, filters, row count, and readback are recorded without secret values.
