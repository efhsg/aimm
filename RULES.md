# AIMM Agent Rules

This compatibility entrypoint exists for tools that discover a root `RULES.md`.
It is not an independent instruction source.

`CLAUDE.md` is the single canonical source for AIMM agent behavior. Read it
completely before acting and follow the references it defines:

- `.claude/rules/` for binding shared rules;
- `.claude/rules/workflow.md` for environment, validation, and recovery;
- `.claude/config/project.md` for runtime-specific commands and paths;
- `.claude/skills/index.md` for applicable task contracts.

Provider-specific entrypoints may route to these files, but must not override or
duplicate them.
