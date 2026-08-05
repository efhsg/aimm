# Squash Migrations

Migration squashing is exceptional maintenance that replaces an established
history with a generated schema migration and an approved reference-data seed.
Migration count alone is not a reason to run it.

## Operator Boundary

Only an AIMM maintainer may execute a squash in an explicitly approved host
environment and maintenance window. The repository's binding agent workflow,
approval gates, recovery rules, and evidence checklist live in
`.claude/skills/squash-migrations.md`; this page is an operator overview.

Before generation, the owner must approve the exact repository and database
targets, migration file set, full backup, tested disposable restore, schema and
reference-data baselines, validation targets, and recovery owner. Credentials
must remain in the approved runtime secret channel.

## Lifecycle

1. Capture repository identity, complete migration history, schema fingerprint,
   reference-data counts, backup checksum, and successful restore evidence.
2. Generate the two migration files on the approved host and stop for complete
   file and hash readback.
3. Apply the generated set only to separate disposable application and test
   restores. Compare schema details, migration history, and approved seed data
   with the baseline.
4. Obtain a second approval identifying the final target, remaining risks, and
   exact recovery action before any apply or cleanup.
5. Read back the final state and report `SUCCESS`, `FAILED`, or `RECOVERED` with
   evidence.

The expected generated files are a timestamped squashed-schema migration and a
timestamped initial-seed migration. The current host command and full execution
contract are intentionally maintained only in the canonical skill.

## Non-Negotiable Safety Rules

- Never run the workflow from the PromptManager runner.
- Never apply generated migrations to the source database used to derive them.
- Never use `migrate/fresh`, database reset, wildcard deletion, or recursive
  cleanup as a shortcut.
- Keep the original migrations and backup until validation, owner review, and
  the approved retention point are complete.
- Stop on any stale pre-state, partial failure, restore failure, or unexplained
  schema or seed difference.

Schema introspection can miss vendor-specific expressions, collations,
triggers, defaults, and `ON UPDATE` behavior. Those details require explicit
comparison during disposable-target validation.
