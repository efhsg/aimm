---
allowed-tools: Bash
description: Commit staged changes and push to origin
---

# Commit and Push

Commit staged changes and push to origin.

## Steps

### 1. Verify staged changes exist

```bash
git diff --staged --stat
```

If no staged changes, report and stop.

### 2. Determine commit message

Follow this order:

1. **If `$ARGUMENTS` is provided** → use it as the commit message
2. **If a commit message was previously suggested in this conversation** → use that message
3. **Otherwise** → analyze the staged changes and generate a commit message per `.claude/rules/commits.md`:
   - Run `git diff --staged` to understand the changes
   - Choose the appropriate type (feat, fix, refactor, docs, etc.)
   - Write a concise description of what changed and why

### 3. Commit

```bash
git commit -m "MESSAGE"
```

**Do NOT add `Co-Authored-By` or AI attribution.**

### 4. Push

```bash
git push origin HEAD
```

Report success or failure.

## Task

$ARGUMENTS
