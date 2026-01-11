---
allowed-tools: Bash, Read
description: Commit staged changes and push to origin
---

# Commit and Push

Commit staged changes and push to origin.

## Steps

### 1. Check for changes

```bash
git diff --staged --stat
git diff --stat
```

- If **staged changes exist** → proceed to step 2
- If **no staged but unstaged changes exist** → stage them with `git add -A`, then proceed
- If **no changes at all** → report "Nothing to commit" and stop

### 2. Determine commit message

Follow this order:

1. **If `$ARGUMENTS` is provided** → use it as the commit message
2. **If a commit message was previously suggested in this conversation** → use that message
3. **Otherwise** → generate a commit message:
   - Read `.claude/rules/commits.md` for format rules
   - Run `git diff --staged` to understand the changes
   - Choose the appropriate type (feat, fix, refactor, docs, test, chore)
   - Write a concise description of what changed and why

### 3. Commit

Use HEREDOC for proper formatting (handles multi-line messages):

```bash
git commit -m "$(cat <<'EOF'
TYPE(scope): description

Optional body explaining why.
EOF
)"
```

**Do NOT add `Co-Authored-By` or AI attribution.**

### 4. Push

```bash
git push origin HEAD
```

**If push fails:**
- If rejected due to remote changes → run `git pull --rebase origin HEAD` then retry push
- If other error → report the error and stop

Report success with commit hash.

## Task

$ARGUMENTS
