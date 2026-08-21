---
allowed-tools: AskUserQuestion, Bash(git rev-parse:*), Bash(git branch --show-current), Bash(git status --short), Bash(git remote), Bash(git for-each-ref:*), Bash(git switch -c:*), Bash(git fetch:*), Bash(git push -u:*)
description: Create a local AIMM task branch from an explicitly confirmed base without implicit network operations
label: Create Branch
min_level: standard
argument-hint: '{type} {description} [base=<local-ref>]'
---

# Create New Branch

Load and follow `.claude/skills/new-branch.md`.

## Task

Create an AIMM task branch for: $ARGUMENTS

The skill owns the valid branch types, the naming rules, and the separate
authorization required for every network action.
