# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Prime Directive

**MANDATORY FOR EVERY CODE CHANGE:**

Before writing or modifying any code, you MUST:
1. Verify the change complies with `.claude/rules/coding-standards.md`
2. Verify folder placement complies with `.claude/rules/architecture.md`
3. Verify security compliance with `.claude/rules/security.md`
4. Follow `.claude/rules/testing.md` when writing tests
5. Check `.claude/skills/index.md` for relevant skills to load into context
6. Follow `.claude/rules/commits.md` when committing

## Session Start

When starting a new session, familiarize yourself with relevant parts of the codebase before making changes. Ask clarifying questions if requirements are unclear.

## Behavioral Guidelines

- **Research before action**: Do not jump into implementation or change files unless clearly instructed. When the user's intent is ambiguous, default to providing information, doing research, and providing recommendations rather than taking action. Only proceed with edits, modifications, or implementations when the user explicitly requests them.
- **Read before answering**: Never speculate about code you have not opened. If the user references a specific file, read it before answering. Investigate and read relevant files BEFORE answering questions about the codebase. Never make claims about code before investigating unless certain of the correct answer.
- **Parallel tool calls**: If you intend to call multiple tools and there are no dependencies between the calls, make all independent tool calls in parallel. Maximize parallel execution for speed and efficiency. Only call tools sequentially when parameters depend on previous results.
- **Summarize completed work**: After completing a task that involves tool use, provide a quick summary of the work done.
- **File deletion**: Only delete files without explicit permission if they are tracked by git (can be restored). Always ask before deleting untracked files.

## Project Overview

AIMM is a **financial data system** for investment analysis. Built with PHP 8.x and Yii 2 framework.

**Domain**: Collect financial data from public sources, validate completeness, and generate analysis reports.

**Key principle**: Data provenance — every metric must have a traceable source.

## Project Configuration

See `.claude/config/project.md` for:
- Commands (linter, tests, database, docker)
- File structure and path mappings
- Test path conventions
- External integrations

**Quick reference:**
```bash
docker exec aimm_yii php -d register_argc_argv=1 vendor/bin/codecept run unit  # Run tests
docker exec aimm_yii vendor/bin/php-cs-fixer fix                                # Run linter
```

## Architecture

See `.claude/rules/architecture.md` for complete folder taxonomy and patterns.

**Source code:** `yii/src/` | **Tests:** `yii/tests/unit/`

## Commits

Claude Code adds `Co-Authored-By` automatically. To follow project rules (no AI attribution):
- Let Claude Code stage changes (`git add`)
- Make the commit manually: `git commit -m "TYPE(scope): description"`
- Or use `/finalize-changes` which suggests a commit message without committing

## Slash Commands

- `/finalize-changes` — Validate changes, run linter and tests, prepare commit
- `/review-changes` — Review code changes for correctness, style, and project compliance
- `/new-branch` — Create a new feature or fix branch
- `/cp` — Commit staged changes and push to origin
- `/squash-migrations` — Consolidate migrations with backup and verification

## Skills

Check `.claude/skills/index.md` for reusable task patterns. When working:
- Load only needed skills to minimize context
- Create new skills for recurring patterns not yet covered
- Keep the skills index current

## Code Review

Before finalizing, run `/finalize-changes` which verifies rules compliance, linter, and tests.
For detailed review criteria, see `.claude/skills/review-changes.md`.

## Definition of Done

- Read and followed shared rules
- Checked skills index for applicable skills
- Used approved folder taxonomy
- Added tests for new logic
- Ran linter before commit (`/finalize-changes`)
- Commit message follows format
