# Implementatieplan: AIMM semantische specreview

**Feature-map:** `.ai/features/aimm-review-spec/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de PromptManager-combinatie `/review-spec` naar AIMM als read-only sectiereview na `/validate-spec`.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/review-spec.md`
- `/var/www/worktree/promptmanager/.claude/skills/review-spec.md`

Doel:

```text
.claude/commands/review-spec.md         (nieuw: dunne wrapper)
.claude/skills/review-spec.md           (nieuw: AIMM-skill)
.claude/skills/index.md                 (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Behoud de sectiegewijze review, maximaal drie bevindingen per sectie, urgentie en geen automatisch verdict.
- Vervang PromptManager-actoren en dataflows door AIMM-provenance, bronconflict, periode, eenheid, ontbrekende waarden en determinisme.
- Gebruik `/validate-spec` als eenvoudige precondition.
- Houd de review adviserend; voeg geen statusmutatie, hook of CI-integratie toe.

## 4. Stappen

1. Poort de wrapper met één specpad als invoer.
2. Poort de controlelijst per aanwezige PRD-sectie.
3. Vervang uitsluitend de PromptManager-domeinvoorbeelden door AIMM-voorbeelden.
4. Registreer command en skill.

## 5. Verificatie

- Ongeldige spec verwijst terug naar `/validate-spec`.
- Geldige spec krijgt per aanwezige sectie maximaal drie bevindingen of expliciet geen bevinding.
- Een financiële flow zonder relevante provenance wordt zichtbaar gemeld.
- De skill geeft geen pass/fail-verdict en wijzigt geen bestand.

## 6. Klaar wanneer

- De AIMM-combinatie dezelfde semantische reviewkennis als PromptManager bevat, toegespitst op AIMM.
- Wrapper, skill en registratie zijn de enige wijzigingen.
- AC1–AC6 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
