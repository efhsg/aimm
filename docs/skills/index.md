# AIMM Skills Index

Skills are repeatable tasks with defined inputs, outputs, and completion criteria.

For code reference documentation, see [docs/reference/](../reference/).

## Skills

| Skill | Description |
|-------|-------------|
| [access-database-from-host](meta/access-database-from-host.md) | Access the `aimm` database from the host machine without leaking credentials. |
| [upgrade-php-version](meta/upgrade-php-version.md) | Upgrade PHP version (Docker + Composer) and validate the stack. |
| [create-migration](meta/create-migration.md) | Create Yii2 database migrations with proper structure and rollback. |
| [review-changes](meta/review-changes.md) | Review code changes for correctness, style, and project compliance. Invoked via `/review-changes`. |

## Reference Documentation

API and architecture documentation for implemented code:

- [Collection handlers](../reference/collection/) — CollectDatapoint, CollectCompany, CollectMacro, etc.
- [Shared components](../reference/shared/) — Provenance recording, not-found handling
