---
allowed-tools: Bash, AskUserQuestion
description: Create a local AIMM task branch from an explicitly confirmed base without implicit network operations
label: Create AIMM Branch
min_level: standard
argument-hint: '{type} {description} [base=<local-ref>]'
---

# Create New Branch

Load and follow `.claude/skills/new-branch.md`.

## Task

Create an AIMM task branch for: $ARGUMENTS

Valid types are `feature`, `fix`, `refactor`, and `chore`. Fetch and remote
publication require separate explicit choices; never pull, merge, or rebase.
