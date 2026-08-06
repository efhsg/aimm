# Implementatieplan: AIMM specgeneratie

**Feature-map:** `.ai/features/aimm-new-spec/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de PromptManager-combinatie `/new-spec` naar AIMM: een dun command en een skill die een nieuwe AIMM-spec uit een sjabloon of één Markdown-bron maakt zonder bestaand werk te overschrijven.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/new-spec.md`
- `/var/www/worktree/promptmanager/.claude/skills/new-spec.md`

Doel:

```text
.claude/templates/spec-prd.md          (nieuw: AIMM-sjabloon)
.claude/commands/new-spec.md           (nieuw: dunne wrapper)
.claude/skills/new-spec.md             (nieuw: AIMM-skill)
.claude/skills/index.md                (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Neem het generatie-, no-overwrite-, slug- en gap-reportgedrag uit PromptManager over.
- Vervang PromptManager-domeinaanwijzingen door AIMM-provenance, periode, eenheid en ontbrekende-data-aandacht.
- Gebruik contractrevisie `AIMM-PRD-1` en de kopmapping uit de accepted spec.
- Houd generatie in de skill; voeg geen apart generatorprogramma toe.

## 4. Stappen

1. Maak het AIMM-sjabloon op basis van de accepted PRD-structuur.
2. Poort de wrapper en pas alleen argumentvoorbeelden en paden aan.
3. Poort de skill voor greenfield- en `--from`-modus, inclusief no-overwrite en zichtbare gaps.
4. Registreer command en skill.

## 5. Verificatie

- Nieuwe naam maakt één draftspec met datum, revisie en unieke slug.
- Een bestaande `spec.md` blijft ongewijzigd.
- Een onbekende bronkop blijft zichtbaar in het gap-report.
- Meerdere kandidaatfeatures vereisen een scopekeuze vóór schrijven.
- Geen PromptManager-productterm blijft als AIMM-instructie staan.

## 6. Klaar wanneer

- De AIMM-combinatie dezelfde generatiekennis als de PromptManager-bron bevat.
- Alleen sjabloon, wrapper, skill en registratie zijn toegevoegd.
- AC1–AC7 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
