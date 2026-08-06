# Implementatieplan: AIMM skill-evaluatie

**Feature-map:** `.ai/features/aimm-evaluate-skill/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de PromptManager-combinatie `/evaluate-skill` naar AIMM als read-only beoordeling van de vraag of een skill haar eigen doel bereikt.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/evaluate-skill.md`
- `/var/www/worktree/promptmanager/.claude/skills/evaluate-skill.md`

Doel:

```text
.claude/commands/evaluate-skill.md      (nieuw: dunne wrapper)
.claude/skills/evaluate-skill.md        (nieuw: AIMM-skill)
.claude/skills/index.md                 (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Behoud peervergelijking, passende rubric, open beoordeling en expliciete confidence.
- Laat een AIMM-skill of één expliciet aangewezen externe kandidaat als read-only bron toe.
- Gebruik AIMM-regels en runtimegrenzen als autoriteit; PromptManager is alleen bronmateriaal.
- Neem geen `/optimize-skill`-afhankelijkheid, metadata-migratie of applymodus over.

## 4. Stappen

1. Poort de wrapper voor één target en optionele transfermodus.
2. Poort de drie beoordelingsdimensies en vereenvoudig targetresolutie tot naam of expliciet pad.
3. Vervang PromptManager-rubricvoorbeelden door AIMM-workflow-, validatie- en provenancevragen.
4. Registreer command en skill.

## 5. Verificatie

- AIMM-skill met peers: bewijs en confidence per dimensie.
- Skill zonder voldoende peers: overige beoordeling gaat door.
- Externe kandidaat: AIMM-fit wordt beoordeeld zonder het bronbestand te wijzigen.
- Conflicterende projectregel: AIMM-regel is leidend.

## 6. Klaar wanneer

- De AIMM-combinatie de semantische evaluatiekennis uit PromptManager bevat zonder ondersteunend skillframework.
- Wrapper, skill en registratie zijn de enige wijzigingen.
- AC1–AC6 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
