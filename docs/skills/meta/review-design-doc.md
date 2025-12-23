---
name: review-design-doc
description: Perform a structured, critical review of a design document against stated principles, architectural taxonomy, type-safety, and security constraints.
model: GPT-5.2 Thinking
area: meta
provides:
  - design_review
  - risk_assessment
  - architectural_feedback
depends_on:
  - docs/RULES.md
---

# ReviewDesignDoc

Critically review a design document to surface architectural risks, gaps, and non-compliance with project principles and constraints.

## When to use
- A user asks for a peer review or design review of a document in `docs/design/`.
- The review must be structured and aligned to project principles (e.g., data quality over speed).

## Inputs
- `designDocPath`: path to the design document (e.g., `docs/design/phase-1-collection.md`)
- `reviewObjectives`: explicit goals or constraints to audit against (optional)
- `areasOfConcern`: list of specific topics to address (optional)
- `outputFormat`: required response format (optional)

## Outputs
- Executive rating (pass/fail/caveats)
- Critical risks (ordered by severity)
- Architectural refinements (targeted improvements)
- Security and rate limiting guidance
- Open questions or assumptions (if needed)

## Non-goals
- Do not implement code changes.
- Do not rewrite the design document unless explicitly requested.
- Do not add new requirements beyond the stated objectives.

## Review checklist

### 1) Principle alignment
- Data quality over speed: verify provenance, validation depth, and schema strictness.
- No silent failures: errors are logged or surfaced.

### 2) Taxonomy compliance
- No `services/` or `helpers/`; use handlers, adapters, transformers, validators, factories, queries.

### 3) Provenance integrity
- SourceLocator and retrieval metadata must remain intact through adapters → DTOs → datapack.

### 4) Yii 2 best practices
- DI container usage is explicit and consistent.
- Console commands delegate logic to handlers.

### 5) Type safety and immutability
- PHP 8.2 features used appropriately (readonly DTOs, constructor promotion, DNF types).
- DTOs avoid arrays when a typed object is appropriate.

### 6) Resiliency and scalability
- Error and retry strategy is explicit.
- High cardinality inputs (50+ peers) do not break orchestration.

### 7) Schema safety
- JSON Schemas reject malformed or ambiguous data.
- DataPoint shapes require provenance and method metadata.

## Review algorithm
1. Read the design document and extract declared principles, flows, and data structures.
2. Map the architecture to the taxonomy and identify forbidden folder/role usage.
3. Trace provenance metadata across layers (adapter → factory → datapack DTO).
4. Audit exception handling and retry behavior for resilience.
5. Check DTO immutability and strict typing usage.
6. Inspect schema excerpts for strictness and data-quality enforcement.
7. Compile findings by severity; suggest minimal refinements.

## Output format (strict)

### Selected skills
- `<skill-name>` — `docs/skills/.../file.md` — one-line reason

### Plan
1. …
2. …

### Implementation
- Files changed:
  - `path/to/file` — summary of change

### Tests
- `tests/...` — scenarios covered

### Notes
- Assumptions
- Edge cases
- Migration/rollout notes (if any)

## Definition of Done
- Review addresses all stated objectives and areas of concern.
- Findings are specific, actionable, and reference the design doc sections.
- Output follows the required format exactly.
