# Implementatieplan: AIMM veilige branchstart

**Feature-map:** `.ai/features/aimm-new-branch/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Werk de bestaande AIMM-combinatie `/new-branch` bij met de bruikbare PromptManager-kennis, maar behoud AIMM's expliciete grenzen voor lokale branchcreatie, fetch en push.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/new-branch.md`
- `/var/www/worktree/promptmanager/.claude/skills/new-branch.md`

Doel:

```text
.claude/commands/new-branch.md          (bestaande wrapper bijwerken)
.claude/skills/new-branch.md            (bestaande AIMM-skill bijwerken)
.claude/skills/index.md                 (beschrijving bijwerken)
```

## 3. AIMM-aanpassingen

- Neem invoer, branchtypen, naamnormalisatie en duidelijke output uit PromptManager over.
- Verwijder impliciete `fetch`, `pull` en `push`; iedere netwerkactie blijft een afzonderlijke keuze.
- Toon vóór mutatie root, branch, HEAD en worktreestatus.
- Behoud bestaand tracked, untracked, staged en unstaged gebruikerswerk.

## 4. Stappen

1. Vergelijk de bestaande AIMM-wrapper en skill met de PromptManager-bron.
2. Neem alleen ontbrekende kennis over die binnen de accepted AIMM-spec past.
3. Pas de flow aan naar read-only inventarisatie, bevestigde lokale creatie en optionele netwerkstappen.
4. Werk de bestaande registratiebeschrijving bij.

## 5. Verificatie

- Schone testrepository: lokale branch zonder fetch of push.
- Dirty testrepository: expliciete bevestiging en behouden wijzigingen.
- Bestaande branch of ontbrekende basis: geen overschrijving.
- Geweigerde netwerkactie: geen netwerkcommand uitgevoerd.
- Gebruik uitsluitend een disposable repository voor muterende scenario's.

## 6. Klaar wanneer

- De bestaande AIMM-combinatie de nuttige PromptManager-kennis bevat zonder automatische netwerkmutaties.
- Geen nieuwe scripts, helpers of branchframeworks zijn toegevoegd.
- AC1–AC5 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
