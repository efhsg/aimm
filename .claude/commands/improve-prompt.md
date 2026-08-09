---
allowed-tools: Read, Glob, Grep, Edit, AskUserQuestion, Bash(git rev-parse --show-toplevel), Bash(git status --short), Bash(test:*)
description: Analyze one AIMM agent-instruction file and apply only explicitly approved improvements
label: Improve AIMM Prompt
min_level: heavy
argument-hint: '{name-or-path} [analyze]'
---

# Improve Prompt

Load and follow `.claude/skills/improve-prompt.md`.

## Task

Improve this AIMM agent-instruction file: $ARGUMENTS

Accept exactly one target: a bare capability name or a readable path under
`.claude/`, `CLAUDE.md`, or a provider entrypoint. Use `analyze` to stop after
the report.

Analysis is read-only. Editing requires the skill's approval gate, stays inside
the single approved target, and never widens `allowed-tools`, weakens a deny
boundary, or stages, commits, or pushes. Route a feature spec to
`/validate-spec`, application code to `/review-changes`, and ecosystem-wide
drift to `/audit-skills`.
