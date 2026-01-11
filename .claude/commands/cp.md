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

- If `$ARGUMENTS` is provided, use it as the commit message
- Otherwise, use the commit message previously suggested in this conversation
- If no message was suggested, generate one per `.claude/rules/commits.md`

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
