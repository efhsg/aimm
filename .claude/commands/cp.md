---
allowed-tools: Bash, Read
description: Commit scoped staged changes and push only when explicitly requested
---

# Commit and Push

Commit only the approved staged scope. Commit and push are separate external
mutations and each requires an explicit request from the user.

## Preconditions

- Read `CLAUDE.md`, `.claude/rules/workflow.md`, and
  `.claude/rules/commits.md`.
- Confirm the active worktree, branch, and remote target.
- Stop if commit was not explicitly requested.
- Do not push unless the current request explicitly includes push approval.

## Steps

### 1. Check for changes

```bash
git diff --staged --check
git diff --staged --stat
git diff --staged
git status --short
```

- If **staged changes exist** → proceed to step 2
- If **no staged changes exist** → report which approved paths still need staging and stop
- If **staged changes include files outside the approved scope** → stop and report them
- If **no changes exist** → report "Nothing to commit" and stop

Never run `git add -A`; never stage unrelated tracked or untracked files.
Read the complete staged diff even when the user supplied a commit message or a
message was suggested earlier in the conversation.

### 2. Determine commit message

Follow this order:

1. **If `$ARGUMENTS` is provided** → use it as the commit message
2. **If a commit message was previously suggested in this conversation** → use that message
3. **Otherwise** → generate a commit message:
   - Read `.claude/rules/commits.md` for format rules
   - Choose the appropriate type (feat, fix, refactor, docs, test, chore)
   - Write a concise description of what changed and why

### 3. Commit

Use the approved message without adding attribution:

```bash
git diff --staged --check
git diff --staged
git status --short
git commit -m "TYPE(scope): description"
```

Stop if this final readback differs from the approved staged content or includes
an unapproved path.

**Do NOT add `Co-Authored-By` or AI attribution.**

Record the commit hash and read `git status --short` back.

### 4. Push only when explicitly approved

If push was not explicitly requested, stop after reporting the local commit.

```bash
git push origin HEAD
```

**If push fails:**
- Report the complete error and stop.
- Do not pull, rebase, merge, force-push, or retry automatically.
- Preserve the local commit as the recovery anchor.

Report the commit hash, branch, push target, and final status.

## Task

$ARGUMENTS
