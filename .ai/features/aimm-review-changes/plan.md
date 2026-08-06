# Implementatieplan: AIMM review-changes optimalisatie

**Feature-map:** `.ai/features/aimm-review-changes/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Werk de bestaande AIMM-combinatie `/review-changes` bij met de bruikbare PromptManager-reviewkennis en vervang alle PromptManager-domeinchecks door AIMM-controles.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/review-changes.md`
- `/var/www/worktree/promptmanager/.claude/skills/review-changes.md`

Doel:

```text
.claude/commands/review-changes.md      (bestaande wrapper bijwerken)
.claude/skills/review-changes.md        (bestaande AIMM-skill bijwerken)
.claude/skills/index.md                 (beschrijving bijwerken)
```

## 3. AIMM-aanpassingen

- Behoud volledige-filelezing, defectfase vóór designfase, severity, confidence en concrete `file:line`-bevindingen.
- Gebruik uitsluitend AIMM-coding-, architectuur-, security- en testregels.
- Voeg AIMM-controles toe voor provenance, periode, eenheid, ontbrekende data en fail-closed gedrag wanneer relevant.
- Houd de review read-only; neem geen `Fix all`-modus of PromptManager-productchecks over.

## 4. Stappen

1. Vergelijk de bestaande AIMM-combinatie met de PromptManager-bron.
2. Neem de reviewfasen, edge cases, outputvorm en confidencekennis over.
3. Vervang alle PromptManager-domeininhoud door AIMM-inhoud en verwijder niet-toepasselijke checks.
4. Werk de bestaande registratiebeschrijving bij.

## 5. Verificatie

- Geen wijzigingen: duidelijke stop zonder kunstmatig oordeel.
- Docs-only: alleen toepasselijke documentchecks.
- PHP-wijziging: volledige file, relevante callers en mapped tests beoordeeld.
- Financiële wijziging: provenance en missing-data-gedrag expliciet beoordeeld.
- Runnerbeperking: hostcheck als handoff, niet als geslaagd.

## 6. Klaar wanneer

- De AIMM-review dezelfde nuttige reviewstructuur als PromptManager bevat zonder PromptManager-regels of mutatiemodus.
- Alleen bestaande wrapper, skill en registratie zijn gewijzigd.
- AC1–AC6 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
