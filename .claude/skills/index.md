# Skills Index

Project-specific skills for Claude Code. Check this index to find relevant skills to load into context.

## Project Configuration

For commands, paths, and environment: `.claude/config/project.md`

## Available Skills

| Skill | File | Use When |
|-------|------|----------|
| Access Database | `access-database-from-host.md` | Querying DB from host without docker exec |
| Create Migration | `create-migration.md` | Adding/modifying database schema |
| Finalize Changes | `../commands/finalize-changes.md` | Validating changes, running linter/tests, preparing commit |
| New Branch | `new-branch.md` | Starting work on a new feature or fix branch |
| Review Changes | `review-changes.md` | Code review, PRs, pre-commit checks |
| Squash Migrations | `squash-migrations.md` | Consolidating migrations with backup and verification |
| Upgrade PHP | `upgrade-php-version.md` | Upgrading PHP in Docker/Composer |

## Reference Documentation

For code reference documentation, see `docs/reference/`:

- [Collection handlers](../../docs/reference/collection/) — CollectDatapoint, CollectCompany, CollectMacro, etc.
- [Shared components](../../docs/reference/shared/) — Provenance recording, not-found handling
