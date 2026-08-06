# Skills Index

Project-specific skills for Claude Code. Check this index to find relevant skills to load into context.

`CLAUDE.md` remains the canonical AIMM instruction source. Skills add bounded
task contracts and cannot override its workflow or safety rules.

## Project Configuration

For commands, paths, and environment: `.claude/config/project.md`

## Available Skills

| Skill | File | Use When |
|-------|------|----------|
| Access Database | `access-database-from-host.md` | Read-only host query through a maintainer-provisioned option file |
| Audit Configuration | `../commands/audit-config.md` | Read-only audit of AIMM agent instructions, registrations, and runtime claims |
| Commit and Push | `../commands/cp.md` | Explicitly approved scoped commit and optional push |
| Create Migration | `create-migration.md` | Schema change validated on application and test schemas |
| Finalize Changes | `../commands/finalize-changes.md` | Environment-aware validation, handoff, and scoped staging |
| Frontend Design | `frontend-design.md` | Building/modifying UI across admin, docs, or PDF reports |
| New Branch | `new-branch.md` | Starting work on a new feature or fix branch |
| Review Changes | `review-changes.md` | Code review, PRs, pre-commit checks |
| Squash Migrations | `squash-migrations.md` | Exceptional, separately approved squash with restore proof |

## Reference Documentation

For code reference documentation, see `docs/reference/`:

- [Collection handlers](../../docs/reference/collection/) — CollectDatapoint, CollectCompany, CollectMacro, etc.
- [Shared components](../../docs/reference/shared/) — Provenance recording, not-found handling
