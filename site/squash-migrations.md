# Squash Migrations

Migration squashing is exceptional maintenance that replaces an established
migration history with a generated schema migration and an approved
reference-data seed migration. A large migration count or a new environment is
not sufficient reason to run it.

## Safety Boundary

Only an AIMM maintainer may execute a squash, in an explicitly named host
environment and maintenance window. Do not run it from the PromptManager runner.

Before generation, record and approve:

- the worktree, branch, HEAD, and complete repository status;
- the application and test database identities and migration histories;
- every migration file that will be archived;
- a full schema-and-data backup, checksum, retention policy, and successful
  restore test on a disposable target;
- the schema fingerprint and reference-data counts;
- the recovery owner and exact recovery action.

Never include database credentials in commands, logs, or review artifacts. Use
the approved runtime secret mechanism.

## Generation

After the first approval gate, the maintainer may generate and archive the
migration set:

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

Expected generated files:

1. `m{timestamp}_squashed_schema.php` — database structure.
2. `m{timestamp}_initial_seed.php` — approved reference data, with
   `data_source` as the default seed table.

The command also moves existing migrations into `yii/migrations/archived/`.
Stop immediately after generation. Read every generated and archived file,
record hashes, and confirm that the exact file set matches the approval.

## Validation

Restore the pre-state backup to separate disposable application and test
targets. Apply only the generated migration set there, then compare the result
with the captured pre-state:

- tables, columns, types, defaults, collations, and expressions;
- indexes and foreign keys;
- migration history;
- approved reference-data rows and counts.

Any unexplained difference or partial command failure stops the workflow.
Schema introspection may not reproduce vendor-specific expressions, triggers,
or `ON UPDATE` behavior, so those details require explicit comparison.

## Apply Approval

Before applying anywhere else, obtain a second approval that identifies the
exact target, validation evidence, remaining risks, and recovery action.

Do not use `migrate/fresh`, a database reset, wildcard deletion, or recursive
cleanup as part of this workflow. Do not apply generated migrations to the
source database used to derive them. Keep the original migrations and backup
until validation, owner review, and the approved retention point are complete.

After apply or recovery, read back the final schema, migration history,
reference-data counts, repository status, and backup availability. Report the
outcome as `SUCCESS`, `FAILED`, or `RECOVERED`, with the associated evidence.
