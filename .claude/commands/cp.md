---
allowed-tools: Read, Bash(git status --short), Bash(git diff --staged:*), Bash(git branch --show-current), Bash(git remote -v), Bash(git rev-parse --show-toplevel), Bash(git log -1:*), Bash(git commit -m:*), Bash(git push origin HEAD)
description: Commit the approved staged scope and push it to the remote
label: Commit and Push
min_level: standard
argument-hint: '[commit message]'
---

# Commit and Push

Commit only the approved staged scope, then push it. Invoking this command is
the explicit approval for both mutations; no separate push confirmation is
required.

## Preconditions

- Read `CLAUDE.md`, `.claude/rules/workflow.md`, and
  `.claude/rules/commits.md`.
- Confirm the active worktree, branch, and remote target.
- Stop if the staged content was not approved, or if it contains a path outside
  the approved scope. Approval covers what is staged, not whatever happens to be
  in the worktree.
- Commit without pushing only when the request says so explicitly.

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

When this step stops, report the exact staged and unstaged paths and end with:

```text
Scope aanpassen / Stoppen?
```

**Wait for user input. Do not stage files on the user's behalf.**

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

### 4. Push

Report the commit hash, branch, and remote target, then push:

```bash
git push origin HEAD
```

When the request asked for a commit without a push, stop here instead and end
with:

```text
Publiceren / Lokaal houden / Stoppen?
```

**Wait for user input. Do not push before the user answers.**

**If push fails:**
- Report the complete error and stop.
- Do not pull, rebase, merge, force-push, or retry automatically.
- Preserve the local commit as the recovery anchor.
- End with:

```text
Fout onderzoeken / Lokaal houden / Stoppen?
```

**Wait for user input. Do not resolve the remote divergence automatically.**

Report the commit hash, branch, push target, and final status.

## Task

$ARGUMENTS
