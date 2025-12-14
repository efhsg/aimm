---
name: setup-git-remote
description: Configure the Git remote (`origin`) for this repository and optionally push the initial branch.
area: meta
depends_on:
  - docs/RULES.md
---

# SetupGitRemote

Configure a Git remote for the current repository (typically `origin`) and prepare for the first push.

## Contract

### Inputs

- `repoRoot` (directory): repository root (must contain `.git/`)
- `remoteName` (string): usually `origin`
- `remoteUrl` (string): SSH or HTTPS URL of the hosted repository
- `branch` (string): default branch name (usually `main`)
- `push` (bool): whether to push the branch after configuring the remote

### Outputs

- `git remote -v` shows `remoteName` pointing to `remoteUrl`
- If `push=true`, the local `branch` is pushed and set upstream

### Safety / invariants

- Do not add remotes with embedded credentials in the URL.
- Do not push secrets (ensure `.env` is not tracked).

## Steps

### 1) Preflight

```bash
git rev-parse --is-inside-work-tree
git status
```

### 2) Configure remote

```bash
git remote add origin <remoteUrl>
git remote -v
```

If `origin` already exists:

```bash
git remote set-url origin <remoteUrl>
```

### 3) Push (optional)

```bash
git push -u origin main
```

## Definition of Done

- `git remote -v` reflects the intended URL
- If pushing, `git branch -vv` shows `main` tracking `origin/main`
