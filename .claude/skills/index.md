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
| Evaluate Skill | `evaluate-skill.md` | Read-only semantic evaluation and optional AIMM transfer-fit advice |
| Finalize Changes | `finalize-changes.md` | Fail-closed validation, host handoff, and exact-path staging |
| Frontend Design | `frontend-design.md` | Building/modifying UI across admin, docs, or PDF reports |
| New Branch | `new-branch.md` | Creating a confirmed local task branch without implicit network actions |
| New Spec | `new-spec.md` | Creating one non-overwriting AIMM-PRD-1 functional spec |
| Preflight Workflow | `preflight-workflow.md` | Clarifying ambiguous or risky AIMM work into a read-only scope decision |
| Review Changes | `review-changes.md` | Evidence-based full-file review before finalization |
| Review Spec | `review-spec.md` | Advising section by section on a mechanically valid AIMM spec |
| Squash Migrations | `squash-migrations.md` | Exceptional, separately approved squash with restore proof |
| Validate Spec | `validate-spec.md` | Read-only R1-R12 validation of AIMM functional specs |

## Reference Documentation

For code reference documentation, see `docs/reference/`:

- [Collection handlers](../../docs/reference/collection/) — CollectDatapoint, CollectCompany, CollectMacro, etc.
- [Shared components](../../docs/reference/shared/) — Provenance recording, not-found handling
