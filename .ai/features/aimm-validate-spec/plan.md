# Implementatieplan: AIMM mechanische specvalidatie

**Feature-map:** `.ai/features/aimm-validate-spec/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de PromptManager-combinatie `/validate-spec` naar AIMM als een command/skill-checklist die AIMM-specs volgens vaste R1–R12-regels controleert.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/validate-spec.md`
- `/var/www/worktree/promptmanager/.claude/skills/validate-spec.md`

Doel:

```text
.claude/commands/validate-spec.md       (nieuw: dunne wrapper)
.claude/skills/validate-spec.md         (nieuw: AIMM-regelcontract)
.claude/skills/index.md                 (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Neem R1–R12, stabiele bevindingen en de scheiding tussen mechanische en semantische review over.
- Pas metadata, verplichte secties en verboden implementatie-inhoud aan op `AIMM-PRD-1`.
- Laat `draft` zichtbare auteursgaten hebben; `in-review` en `accepted` mogen die niet hebben.
- Beschrijf de regels rechtstreeks in de skill; voeg geen Python-script, hook, CI-flow of nieuwe regelconfig toe.

## 4. Stappen

1. Poort de wrapper met invoer voor één spec, het sjabloon of `--all`.
2. Poort R1–R12 als vaste, genummerde AIMM-checklist met locatie en reden per bevinding.
3. Voeg AIMM-status- en contractrevisieregels toe.
4. Registreer command en skill en verwijs na een schone controle naar `/review-spec`.

## 5. Verificatie

- Controleer een geldige `draft`, `in-review` en `accepted` spec.
- Controleer minimaal één foutgeval per R-regel.
- Bevestig dat de skill het beoordeelde document nooit wijzigt.
- Bevestig dat dezelfde ongewijzigde invoer dezelfde gerangschikte bevindingen oplevert.

## 6. Klaar wanneer

- De AIMM-skill de bruikbare R1–R12-kennis uit PromptManager bevat zonder runtime-infrastructuur te kopiëren.
- Wrapper, skill en registratie zijn de enige wijzigingen voor deze combinatie.
- AC1–AC7 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
