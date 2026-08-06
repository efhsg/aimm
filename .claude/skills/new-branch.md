---
name: new-branch
description: Safely create a local AIMM feature, fix, refactor, or chore branch from a confirmed base while preserving worktree changes and requiring separate approval for every network action
area: workflow
provides:
  - branch_creation
depends_on:
  - rules/commits.md
  - rules/workflow.md
---

# New Branch

Create one local task branch in the explicitly assigned AIMM worktree. Do not
fetch, pull, merge, rebase, push, stash, discard, or move user changes unless
that distinct action is explicitly authorized.

## Inputs

- `type`: `feature`, `fix`, `refactor`, or `chore`;
- `description`: short task description, preferably 2–4 words;
- `base`: optional local ref; otherwise propose the current branch or local
  `main` from observed state.

Ask for missing or ambiguous input before mutation. A request to create a branch
does not authorize fetch or publication.

Show at most one choice per turn, make its choice line the final non-empty line,
and wait for the answer before continuing.

## Procedure

### 1. Inventory

Read back before any mutation:

```bash
git rev-parse --show-toplevel
git branch --show-current
git rev-parse HEAD
git status --short
git remote
git for-each-ref --format='%(refname:short) %(symref)' refs/heads refs/remotes
```

Confirm the root equals the assigned AIMM worktree. Show tracked, untracked,
staged, and unstaged changes. Treat configured local branches and cached
remote-tracking refs as available without network access; ignore symbolic remote
`HEAD` as a base. If changes exist, explain that branch creation carries them to
the new branch and ask:

```text
Wijzigingen meenemen / Stoppen?
```

Do not stash or remove them.

### 2. Normalize and validate

Normalize the description to lowercase ASCII kebab-case: replace whitespace
with hyphens, remove unsupported characters, collapse repeated hyphens, and trim
hyphens. Form `{type}/{description}` and keep the total under 50 characters.

Reject an empty description, unsupported type, invalid ref syntax, or an
existing local branch. Show the proposed name and base before creation.

Use only a locally available base. If the requested base is absent, explain the
missing ref and ask:

```text
Gerichte fetch / Andere lokale basis / Stoppen?
```

Only `Gerichte fetch` authorizes one bounded `git fetch <remote> <ref>`. Resolve
the remote from the exact names returned by `git remote`, validate the requested
ref, and show the full command before execution. After success, verify the
intended local base with `git rev-parse --verify '<resolved-ref>^{commit}'` and
read the local and remote-tracking refs again. Never use `FETCH_HEAD` as an
implicit base. Fetch does not authorize pull, merge, rebase, push, or a broader
fetch.

### 3. Create locally

After explicit confirmation, create exactly the proposed branch from exactly
the confirmed base:

```bash
git switch -c <branch-name> <base>
```

On failure, stop and report the command result. Do not retry with another base
or history-changing command.

### 4. Read back

Verify:

```bash
git branch --show-current
git rev-parse HEAD
git status --short
git rev-parse --abbrev-ref --symbolic-full-name '@{upstream}'
```

An absent upstream is expected for a local-only branch. Confirm that the branch
and HEAD match the proposal and that pre-existing worktree changes remain
visible.

### 5. Optional publication

Publish only when the requester separately asks for it after local readback.
Show the exact branch and remote, then ask:

```text
Publiceren / Lokaal houden / Stoppen?
```

Only `Publiceren` authorizes `git push -u <remote> <branch-name>`. If it fails,
keep the local branch as the recovery anchor and do not pull, rebase, retry, or
change remotes without a new decision.

After a successful push, repeat the branch, HEAD, upstream, and worktree-status
readback from step 4. Verify that the upstream equals the approved remote branch;
do not infer success from command output alone.

## Output

Report:

- active root;
- created branch and confirmed base;
- HEAD before and after;
- whether existing changes were preserved;
- upstream as `none` or the verified remote ref;
- every network action actually executed, or `none`.

## Completion

- Root, branch, HEAD, and worktree status were shown before mutation.
- Branch type, normalized unique name, and local base were confirmed.
- Existing user work remains visible.
- No network or history-changing action occurred without separate authorization.
- Final branch, HEAD, upstream, and status were read back.
