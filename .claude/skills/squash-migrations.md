---
name: squash-migrations
description: Plan an exceptional migration squash with explicit environment, backup, readback, and recovery approval. Never use as a routine implementation step.
area: database
---

# SquashMigrations

Plan and, only after separate approvals, consolidate existing migrations into a
schema migration and a reference-data seed migration. A large migration count
alone is not authorization to run this workflow.

## Environment Boundary

- Execute only from an explicitly named AIMM host environment with working
  Docker and the documented PHP >=8.5 container.
- The PromptManager runner may inspect repository files and prepare a maintainer
  plan, but must not run Docker, local PHP, database commands, archive cleanup,
  or migration deletion.
- Treat production as a separate change boundary with an owner-approved
  maintenance window, backup, restore proof, and rollback decision.

## Required Inputs and Approvals

Record and obtain explicit approval for all of the following before generation:

1. Active worktree, branch, HEAD, and clean or fully explained status.
2. Exact environment, application database, test database, containers, and
   `yii/migrations/` directory.
3. Exact migration files to archive and exact two files expected as output.
4. A full schema-and-data backup created through the approved runtime secret
   mechanism, including storage target, timestamp, checksum, retention, and a
   tested restore result.
5. Pre-state schema fingerprint, migration history, table/column/index/foreign
   key inventory, and reference-data counts.
6. The validation plan for a disposable restored copy and the recovery owner.
7. A second approval after readback, before any apply, reset, archive removal,
   or production action.

Never place database usernames, passwords, tokens, connection strings, or other
credential literals in this skill, shell history, logs, or evidence. Use named
runtime variables and the approved secret manager.

## Safety Invariants

- Do not use `migrate/fresh`, database reset, wildcard deletion, recursive
  cleanup, or destructive Git reset as a default step.
- Do not apply generated migrations to the source database used to derive them.
- Keep original migrations and backups until validation, owner review, and the
  retention decision are complete.
- Validate application and isolated test schemas separately.
- Any missing backup field, stale pre-state, schema mismatch, seed mismatch, or
  partial command failure stops the workflow.

## Approved Workflow

### 1. Read-only preflight

- Capture Git and database identities, migration history, schema fingerprint,
  reference-data counts, and exact restore anchor.
- Confirm that the approved backup completed and that restoration succeeded on
  a disposable target.
- Stop if another actor changed HEAD, migration files, schema, or data counts.

### 2. Generate only

After the first approval gate, an AIMM maintainer may run:

```bash
docker exec aimm_yii php yii squash-migrations --archive --with-seed
```

Expected repository output:

1. `m{timestamp}_squashed_schema.php`
2. `m{timestamp}_initial_seed.php`

Stop immediately after generation. Read every generated and archived file,
record their hashes, and compare the exact file set with the approved target.

### 3. Validate on disposable restored targets

- Restore the pre-state backup to a disposable application target and a
  separate disposable test target.
- Apply the generated migration set only there.
- Compare normalized tables, columns, types, indexes, foreign keys, migration
  history, and approved reference-data counts with the pre-state.
- Record complete command output and exit codes. Any unexplained difference is
  a failure; formatting or ordering differences require explicit review rather
  than silent acceptance.

### 4. Second approval gate

Present the generation diff, schema comparison, reference-data comparison,
restore proof, remaining risks, exact apply target, and exact recovery action.
Do not proceed without a separate explicit owner decision.

### 5. Apply or recover

- Apply only to the exact approved target through the maintainer's controlled
  deployment flow.
- On failure, stop all later actions and restore through the pre-approved backup
  path. Do not improvise a fresh migration or destructive cleanup.
- Read the final schema, migration history, reference-data counts, repository
  status, and backup availability back after apply or recovery.

## Known Limitations

- Schema introspection may not reproduce every vendor-specific expression,
  default, collation, trigger, or `ON UPDATE` behavior.
- Seed generation covers only explicitly approved reference tables; never infer
  that other business data may be discarded or regenerated.

## Definition of Done

- [ ] Exact environment, targets, files, owner, and maintenance boundary approved
- [ ] Full backup recorded with checksum and successful disposable restore proof
- [ ] Pre-state schema, history, reference counts, HEAD, and worktree captured
- [ ] Generated and archived files read back with hashes
- [ ] Application and test disposable targets validate without unexplained drift
- [ ] Second approval obtained before any apply or cleanup
- [ ] Final apply or recovery read back completely
- [ ] Original migrations and backup retained until the approved retention point
- [ ] Result reported as `SUCCESS`, `FAILED`, or `RECOVERED`, with evidence
