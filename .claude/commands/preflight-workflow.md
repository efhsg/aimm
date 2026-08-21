---
allowed-tools: Read, Glob, Grep, Bash(grep:*), AskUserQuestion
description: Clarify an ambiguous AIMM task into a read-only scope decision before specification or implementation
label: Clarify Workflow Scope
min_level: standard
argument-hint: '{brief or source references}'
---

# Preflight Workflow

Load and follow `.claude/skills/preflight-workflow.md`.

## Task

Clarify this AIMM task before specification or implementation: $ARGUMENTS

If no brief or source reference is supplied, ask for exactly one. Do not start
specification, planning, implementation, or any repository or external mutation.
