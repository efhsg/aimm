# Implementatieplan: AIMM preflight-workflow

**Feature-map:** `.ai/features/aimm-preflight-workflow/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de PromptManager-combinatie `/preflight-workflow` naar AIMM en vervang de productgerichte vragen door AIMM-vragen over scope, provenance, periode, eenheid, ontbrekende data en runtimegrenzen.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/preflight-workflow.md`
- `/var/www/worktree/promptmanager/.claude/skills/preflight-workflow.md`

Doel:

```text
.claude/commands/preflight-workflow.md (nieuw: dunne wrapper)
.claude/skills/preflight-workflow.md   (nieuw: AIMM-skill)
.claude/skills/index.md                (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Behoud research-vóór-vragen, één blokkerende vraag per beurt en het vraagbudget van vijf plus expliciete verlenging met drie.
- Vervang Notes, Contexts, prompts en pipelines door relevante AIMM-bronnen en flows.
- Houd de preflight volledig read-only en laat haar eindigen met een compact scopebesluit.
- Voeg geen opslag, sessiemodel of aparte state-machine toe; gebruik de beschikbare conversatiecontext.

## 4. Stappen

1. Poort de wrapper en vervang alleen beschrijving en AIMM-voorbeelden.
2. Poort triage, bronlabels, vraaglus, stopmomenten en outputcontract.
3. Voeg de AIMM-specifieke blokkades uit de accepted spec toe.
4. Registreer command en skill.

## 5. Verificatie

- Triviale taak: gemotiveerde skip.
- Ambigue financiële taak: één vraag naar de richtingbepalende ontbrekende keuze.
- Conflicterende bronnen: conflict zichtbaar, geen verzonnen beslissing.
- Vraagbudget bereikt: samenvatten, verlengen of stoppen.
- Geen repository-, database- of externe mutatie.

## 6. Klaar wanneer

- De AIMM-combinatie dezelfde preflightkennis als PromptManager bevat met alleen domeintermen aangepast.
- Wrapper, skill en registratie zijn de enige wijzigingen.
- AC1–AC6 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
