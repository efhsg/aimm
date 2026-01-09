# New Branch

Create a new feature or fix branch following project naming conventions.

## Persona

Developer starting work on a new feature or bug fix.

## When to Use

- Starting work on a new feature or bug fix
- User runs `/new-branch`

## Inputs

- `type`: feature, fix, refactor, or chore (ask if not provided)
- `description`: Short description, 2-4 words (ask if not provided)

## Algorithm

1. Run in parallel:
   - `git branch --show-current`
   - `git fetch origin main`
2. If not on `main`, ask user for confirmation to continue
3. Use AskUserQuestion to gather:
   - Branch type: feature, fix, refactor, or chore
   - Short description (2-4 words)
4. Sanitize description:
   - Lowercase
   - Replace spaces with hyphens
   - Remove special characters
   - Limit to keep total branch name under 50 chars
5. Generate branch name: `{type}/{sanitized-description}`
6. Create branch: `git checkout -b {branch-name} origin/main`
7. Set upstream: `git push -u origin {branch-name}`
8. Confirm success with `git status`

## Branch Name Format

```
{type}/{description}
```

Examples:
- `feature/add-macro-collection`
- `fix/null-pointer-handler`
- `refactor/consolidate-queries`
- `chore/update-dependencies`

## Output Format

```
Created branch: feature/add-macro-collection
Based on: origin/main
Upstream: origin/feature/add-macro-collection
Status: Ready to start development
```

## Definition of Done

- User confirmed branch details
- Branch created from latest `origin/main`
- User sees confirmation with branch name
