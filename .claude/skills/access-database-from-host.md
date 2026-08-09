---
name: access-database-from-host
description: Query an approved AIMM database read-only from a host through a maintainer-provisioned MySQL option file, without reading, serializing, or exposing credentials.
area: database
provides:
  - host_readonly_database_access
depends_on:
  - rules/security.md
  - config/project.md
---

# Access Database from Host

Run bounded read-only diagnostics against an explicitly approved AIMM database.
The agent must never construct a credential file from raw values: MySQL option
files have their own quoting, comment, and escape rules, so shell interpolation
is not a safe serialization mechanism.

## Environment Boundary

- Use only on an AIMM host with the `mysql` client.
- Do not use in the PromptManager runner. It has no approved database credential
  channel; provide a maintainer handoff instead.
- Stop if the target database, purpose, or allowed query scope is not explicit.

## Required Maintainer Input

The maintainer provisions a dedicated MySQL option file through the approved
secret-management channel and injects only its absolute path as:

```text
AIMM_DB_READONLY_DEFAULTS_FILE
```

The option file must:

- be a regular, non-symlink file owned by the invoking account with mode `0600`;
- contain the exact approved host, port, database, and dedicated read-only
  account in a `[client]` group;
- be created and escaped by the maintainer's credential tooling, not by the
  agent or a shell heredoc;
- have a maintainer-owned expiry and removal path.

The maintainer must verify the account's current grants allow only the required
reads. Application or administrative credentials are not accepted.

## Connection Preflight

Do not open, print, copy, edit, or delete the option file. Validate only its path
and filesystem boundary, then let the MySQL client parse it:

```bash
set -euo pipefail

: "${AIMM_DB_READONLY_DEFAULTS_FILE:?maintainer must inject the approved option-file path}"

case "$AIMM_DB_READONLY_DEFAULTS_FILE" in
  /*) ;;
  *) echo "AIMM_DB_READONLY_DEFAULTS_FILE must be absolute" >&2; exit 1 ;;
esac

test -f "$AIMM_DB_READONLY_DEFAULTS_FILE"
test ! -L "$AIMM_DB_READONLY_DEFAULTS_FILE"
test "$(stat -c '%u' "$AIMM_DB_READONLY_DEFAULTS_FILE")" = "$(id -u)"
test "$(stat -c '%a' "$AIMM_DB_READONLY_DEFAULTS_FILE")" = "600"

mysql --defaults-file="$AIMM_DB_READONLY_DEFAULTS_FILE" \
  --batch --raw \
  -e "SELECT DATABASE() AS database_name, CURRENT_USER() AS database_user;"
```

Confirm the returned database and account match the approved target before any
diagnostic query. Pass no host, port, database, username, or password overrides
on the command line.

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

Error summary for an approved run:

```sql
SELECT severity, error_code, COUNT(*) AS cnt
FROM collection_error
WHERE collection_run_id = 123
GROUP BY severity, error_code
ORDER BY severity, cnt DESC, error_code ASC;
```

Error details for an approved run:

```sql
SELECT severity, error_code, ticker, error_path, error_message, created_at
FROM collection_error
WHERE collection_run_id = 123
ORDER BY ticker ASC, severity ASC, error_code ASC, error_path ASC
LIMIT 200;
```

## Guardrails

- Read only the columns and rows needed for the diagnosis; always use an
  approved identifier and a practical `LIMIT` for detail queries.
- Never place credential values or option-file contents in chat, commands,
  process arguments, logs, or evidence.
- Never use this skill for mutation, schema inspection beyond the approved
  scope, or privilege changes.
- Stop on an unexpected target, unexpected account, permission error, or query
  result outside the approved scope.

## Definition of Done

- The maintainer-provisioned file and read-only grants passed preflight.
- The returned database and account matched the approved target.
- Only approved, bounded read queries ran.
- Target, filters, row count, and result summary were recorded without secrets.
- The maintainer retained responsibility for option-file expiry and removal.
