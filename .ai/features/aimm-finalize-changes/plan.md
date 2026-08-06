# Implementatieplan: AIMM finalize-changes optimalisatie

**Feature-map:** `.ai/features/aimm-finalize-changes/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Maak van de bestaande AIMM `/finalize-changes`-logica dezelfde dunne wrapper-plus-skill-combinatie als in PromptManager, met behoud van AIMM's eigen validatie- en stagingregels.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/finalize-changes.md`
- `/var/www/worktree/promptmanager/.claude/skills/finalize-changes.md`

Doel:

```text
.claude/commands/finalize-changes.md    (bestaande command uitdunnen)
.claude/skills/finalize-changes.md      (nieuw: bestaande AIMM-logica als skill)
.claude/skills/index.md                 (registratie bijwerken)
```

## 3. AIMM-aanpassingen

- Verplaats de bestaande AIMM-finalisatiestappen naar de skill; herschrijf ze niet tot een nieuw systeem.
- Behoud actieve-rootcontrole, volledige scopeset, runner/hostonderscheid, gerichte staging en commitvoorstel zonder commit of push.
- Voeg alleen de specstatuscontrole uit de accepted spec toe.
- Neem geen PromptManager-archiveringsflow, test-runner, brede `git add -A` of andere projectspecifieke tooling over.

## 4. Stappen

1. Maak de bestaande command een dunne verwijzing naar de nieuwe skill.
2. Verplaats de huidige AIMM-stappen naar die skill en behoud de bestaande veilige commands.
3. Voeg de eenvoudige feature-speccontrole toe: featurecode vereist precies één geldige `accepted` spec; document-only specwerk niet.
4. Werk de registratie bij.

## 5. Verificatie

- Config- of documentwijziging: alleen toepasselijke runner-safe checks.
- Productwijziging in de runner: exacte hosthandoff en geen staging vóór bewijs.
- Featurecode zonder accepted spec: stop.
- Goedgekeurde groene scope: alleen expliciete paden staged.
- Geen commit, push, archivering of brede staging.

## 6. Klaar wanneer

- AIMM dezelfde wrapper-plus-skillvorm als PromptManager gebruikt zonder zijn projecttooling te kopiëren.
- De bestaande AIMM-veiligheidsgrenzen intact blijven.
- AC1–AC7 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
