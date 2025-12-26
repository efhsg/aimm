# Agent Workflow

## Skill-Driven Development

For every task:

1. Read project rules first — these apply to everything
2. Check `docs/skills/index.md` — find relevant skills
3. Load only needed skills — minimize context
4. Follow DoD — skill is done when Definition of Done passes
5. Create skills for gaps — if behavior isn't covered, write a skill
6. Update index — keep skill registry current

## Code Review Checklist

Before approving:

- [ ] Follows folder taxonomy
- [ ] No catch-all folders created
- [ ] Type hints present
- [ ] Tests for new logic
- [ ] Provenance recorded for data operations
- [ ] No silent failures
- [ ] Commit messages follow format
