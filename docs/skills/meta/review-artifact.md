---
name: review-artifact
description: Review a design or analysis artifact for correctness, completeness, clarity, risk, and fitness-for-purpose.
area: meta
provides:
  - artifact_review
  - risk_assessment
  - quality_feedback
depends_on:
  - docs/rules/architecture.md
  - docs/rules/coding-standards.md
  - docs/rules/security.md
  - docs/rules/testing.md
---

# ReviewArtifact

Evaluate designs or analyses for correctness, completeness, clarity, risk, and fitness-for-purpose. This skill assesses and recommends; it does not implement.

## When to use

- User asks for a peer review of a design document, RFC, or analysis
- User provides an artifact and asks for feedback or critique
- User wants to validate an approach before implementation

## Inputs

- `artifactContent`: The design or analysis content to review (inline text or file path)
- `artifactType`: Type of artifact — `design` | `analysis` | `auto` (default: `auto`)
  - `design`: Documents proposing architecture, components, or implementation approaches
  - `analysis`: Documents evaluating data, trade-offs, or existing systems
  - `auto`: Infer type from content—treat as `design` if it proposes new structures or behaviors, otherwise `analysis`
- `reviewObjectives`: Specific goals or constraints to audit against (optional)
- `areasOfConcern`: Specific topics to focus on (optional)

## Input format

The artifact must be provided in one of these ways:
1. **Inline**: Content between `<artifact>` tags
2. **File path**: Path to a document (e.g., `docs/design/my-design.md`)
3. **Preceding context**: Artifact in the conversation history, explicitly referenced

If no artifact is identifiable, request it before proceeding.

## Outputs

- Restated goal (one sentence)
- Summary verdict (2-4 sentences: overall quality and fitness-for-purpose)
- Strengths (bullet list)
- Issues categorized by severity (High/Medium require impact and evidence; Critical/Low are brief)
- Assumptions and inferences (labeled explicitly)
- Risks with likelihood, impact, and mitigation
- Prioritized recommendations (specific and testable)

## Non-goals

- Do not rewrite or redesign the artifact
- Do not add requirements not implied by the stated goal
- Do not implement anything
- Do not provide recommendations without specific, testable actions

## Review checklist

### 1. Goal clarity

- Is the stated goal clear and measurable?
- Are success criteria defined?

### 2. Internal consistency

- Do terms have consistent definitions throughout?
- Do different sections contradict each other?

### 3. Completeness

- Does the artifact address all aspects of the stated goal?
- Are there obvious gaps or missing sections?

### 4. Logical soundness

- Do conclusions follow from premises?
- Are trade-offs acknowledged?

### 5. Risk identification

- Are failure modes considered?
- Are dependencies and assumptions explicit?

### 6. Alignment (if project context available)

- Does it comply with architectural taxonomy?
- Does it follow coding standards?
- Does it address security constraints?

## Algorithm

1. Identify the artifact and its stated goal.
2. **Read all dependent rule files** listed in `depends_on` to establish the review baseline.
3. Determine artifact type (design or analysis) if not specified.
4. Read the artifact completely before forming judgments.
5. Evaluate against each checklist item.
6. Label inferred points explicitly as `INFERRED`.
7. Flag assumptions made by the author as `ASSUMED`.
8. Categorize findings by severity.
9. Formulate recommendations that are specific and testable.
10. Verify each recommendation aligns with the stated goal.

## Severity levels

| Level | Criteria | Action |
|-------|----------|--------|
| **Critical** | Fundamental flaws that invalidate the artifact's purpose | Must address before proceeding |
| **High** | Significant gaps or errors that undermine key objectives | Should address before approval |
| **Medium** | Issues that reduce quality or increase risk | Recommended to address |
| **Low** | Minor improvements or clarifications | Consider addressing |

## Handling ambiguity

- **Missing but non-blocking info**: Proceed and flag it
- **Unclear goal**: Choose the most conservative interpretation and state it explicitly
- **Contradictions**: Prioritize explicit statements over inferred intent
- **Missing critical input**: Stop and request only the minimal missing information

## Output format

```
## 1. Restated Goal

(one sentence summarizing what the artifact aims to achieve)

## 2. Summary Verdict

(2-4 sentences: overall quality, fitness-for-purpose, recommendation to proceed/revise/reject)

## 3. Strengths

- (bullet list, or omit section if none)

## 4. Issues & Gaps

### Critical

- (bullet list, or omit subsection if none)

### High

| Issue | Impact | Evidence |
|-------|--------|----------|
| Description | How it affects the goal | Quote or section reference |

(omit table if none)

### Medium

| Issue | Impact | Evidence |
|-------|--------|----------|
| Description | How it affects the goal | Quote or section reference |

(omit table if none)

### Low

- (bullet list, or omit subsection if none)

## 5. Assumptions & Inferences

- ASSUMED (by author): (bullet list, or state "none identified")
- INFERRED (by reviewer): (bullet list, or state "none")

## 6. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Description | Low/Medium/High | Low/Medium/High | Suggested action |

(omit table if none)

## 7. Recommendations (Prioritized)

1. **[Severity]** Specific, testable recommendation
2. (none or list)
```

## Refusal conditions

Refuse the review only if:
- No design or analysis content is provided after requesting it
- The task requests implementation instead of review

## Example

**Input artifact** (inline):

```
<artifact>
## Cache Layer Design

Add Redis caching to reduce API latency.

### Approach
- Cache all GET responses for 1 hour
- Use request URL as cache key
- Invalidate on any POST/PUT/DELETE

### Components
- CacheMiddleware in handlers/
</artifact>
```

**Review output** (abbreviated):

```
## 1. Restated Goal

Add a Redis caching layer to reduce API response latency.

## 2. Summary Verdict

The design addresses the core goal but lacks detail on cache invalidation
granularity and error handling. Recommend revising to address High issues
before implementation.

## 4. Issues & Gaps

### High

| Issue | Impact | Evidence |
|-------|--------|----------|
| Over-broad invalidation | Any write clears entire cache, negating benefits | "Invalidate on any POST/PUT/DELETE" |
| No TTL justification | 1-hour TTL may serve stale data for time-sensitive endpoints | "Cache all GET responses for 1 hour" |

## 5. Assumptions & Inferences

- ASSUMED (by author): Redis is already available in the infrastructure
- INFERRED (by reviewer): "handlers/" placement implies CacheMiddleware is a handler, not middleware

## 7. Recommendations (Prioritized)

1. **[High]** Define per-resource invalidation rules instead of global invalidation
2. **[High]** Specify TTL by endpoint type (e.g., 5 min for prices, 1 hour for static data)
3. **[Medium]** Clarify whether CacheMiddleware is a Yii filter or a handler
```

## Definition of Done

- High/Medium issues include impact and evidence (artifact references)
- Recommendations are specific, testable, and aligned with the stated goal
- Detailed findings appear once; Summary Verdict may reference them without repeating details
- Output follows the required format exactly
- Severity levels are assigned to all issues
