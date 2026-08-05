# AIMM AI Work Artifacts

This directory separates durable, reviewable AIMM task artifacts from local
agent memory. Create content only for a concrete user-requested task.

## Durable Work Roots

- `.ai/features/<slug>/spec.md` — approved feature behavior and scope.
- `.ai/bugfix/<slug>/report.md` — reproducible bug analysis and fix scope.

A task may also contain a durable `plan.md`, `analysis.md`, or supporting
evidence when the task requires it. Keep each artifact traceable to that task;
do not copy unrelated PromptManager prompts, archives, or feature history.

The empty `features/` and `bugfix/` roots are retained with `.gitkeep`. Do not
create a sample slug or placeholder feature merely to populate them.

## Temporary Agent Memory

Local workdirs such as these are runtime memory and must not be committed:

- `implementation/`
- `review/` and numbered review workdirs
- `code-review/` and numbered code-review workdirs
- `proposals/`

Git ignore rules cover these patterns without hiding durable specifications,
reports, analyses, or plans.

## Boundaries

- Never store credentials, private keys, tokens, `.env` contents, or sensitive
  financial source data in AI artifacts.
- Do not treat generated memory as project truth; repository code and approved
  durable artifacts remain the evidence sources.
- Keep PromptManager's project feature-root unset. Its effective default
  `.ai/features` already points to the durable AIMM feature root.
