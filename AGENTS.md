# AGENTS.md — OpenAI Codex Configuration

This is a thin provider entrypoint. It does not define AIMM behavior or duplicate
runtime policy.

## Single Source of Truth

`CLAUDE.md` is the single canonical instruction source for this repository.

Read it completely before acting, then follow its referenced rules,
environment-specific project configuration, and applicable skills.

## Key References

| What | Where |
|------|-------|
| Canonical instructions and project overview | `CLAUDE.md` |
| Rules | `.claude/rules/` |
| Skills | `.claude/skills/index.md` |
| Environment commands and paths | `.claude/config/project.md` |
| Workflow and recovery boundaries | `.claude/rules/workflow.md` |

## Definition of Done

See `CLAUDE.md`; the same criteria apply to every provider.
