---
name: review-changes
description: Review code changes for correctness, style, and project compliance.
area: meta
provides:
  - code_review
  - compliance_check
depends_on:
  - .claude/rules/coding-standards.md
  - .claude/rules/architecture.md
  - .claude/rules/security.md
  - .claude/rules/testing.md
---

# ReviewChanges

Perform a structured code review of staged or unstaged changes against project rules and best practices.

## Persona

- Senior PHP 8.x engineer with 20 years of production PHP experience, including 10 years specializing in Yii 2.
- Write concise, PSR-12-compliant, fully type-hinted code that follows Yii 2 conventions.
- Expert test engineer fluent with Unit and Codeception tests.

## When to use

- User asks to review changes, code, or a PR
- Before finalizing changes for commit
- After implementing a feature to catch issues early

## Inputs

- `scope`: Optional file path, directory, or description to narrow review
- `staged`: If true, review only staged changes (default: review all changes)

## Outputs

- Summary with file count and overall status
- Findings categorized by severity
- Actionable recommendations

## Review checklist

### 1. Correctness

- Logic is correct and handles edge cases
- No obvious bugs or regressions
- Error handling is appropriate (no silent failures)

### 2. Style & Standards

Per `.claude/rules/coding-standards.md`, plus:

- No unnecessary curly braces (single-statement blocks)
- Never fully-qualified class names in method bodies — use imports
- Prefer early returns over deep nesting
- CLASS: Brief intent comment (2-3 lines)
- FUNCTIONS: PHPDoc only for `@throws`; skip obvious `@param`/`@return`
- No commented-out code

### 3. Architecture

Per `.claude/rules/architecture.md`

### 4. SOLID/DRY

Apply judiciously — stop when goal is met, don't over-abstract.

### 5. Security

Per `.claude/rules/security.md`

### 6. Tests

Per `.claude/rules/testing.md`

## Algorithm

1. Run `git status --porcelain` to identify changed files
2. Run `git diff` (or `git diff --staged`) to see specific changes
3. Read each changed file to understand full context
4. Load project rules from `.claude/rules/`
5. Evaluate each file against the review checklist
6. Categorize findings by severity
7. Compile report with actionable recommendations

## Severity levels

| Level | Criteria | Action |
|-------|----------|--------|
| **Critical** | Security vulnerabilities, data corruption risks, breaking changes without migration | Must fix before merge |
| **High** | Bugs, incorrect logic, missing error handling, violated architecture rules | Should fix before merge |
| **Medium** | Missing tests, code style violations, suboptimal patterns | Recommended to fix |
| **Low** | Minor improvements, documentation gaps, naming suggestions | Consider fixing |

## Output format

```
## Review Summary

**Files reviewed:** N files
**Status:** PASS | PASS WITH COMMENTS | NEEDS CHANGES

## Findings

### Critical
- (none or list with file:line references)

### High
- `path/to/file:123` — description of issue

### Medium
- `path/to/file:45` — description of issue

### Low
- `path/to/file:67` — suggestion

## Recommendations

1. Specific action to take
2. ...
```

## Definition of Done

- All changed files reviewed against checklist
- Findings reference specific file and line numbers
- Each finding has clear, actionable description
- Overall status reflects severity of findings
- Output follows the required format
