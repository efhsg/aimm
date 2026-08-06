# Implementatieplan: AIMM configuratie-audit

**Feature-map:** `.ai/features/aimm-audit-config/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort het PromptManager-command `/audit-config` naar AIMM en vervang uitsluitend de PromptManager-inventaris en controles door de AIMM-equivalenten.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/audit-config.md`

Doel:

```text
.claude/commands/audit-config.md       (nieuw: AIMM-versie van het command)
.claude/skills/index.md                (command registreren)
CLAUDE.md                              (slash-command vermelden)
```

PromptManager heeft voor deze workflow bewust geen aparte skill. AIMM houdt dezelfde eenvoudige vorm aan.

## 3. AIMM-aanpassingen

- Vervang PromptManager-modellen, services, RBAC en testclaims door AIMM-bronnen uit `CLAUDE.md`, `.claude/rules/` en `.claude/config/project.md`.
- Gebruik alleen repositorycontroles die in de actieve omgeving uitvoerbaar zijn.
- Houd de audit read-only en rapporteer hostafhankelijke controles als handoff.
- Behoud het rapport met `kritiek`, `waarschuwing` en `informatie`; neem geen fixmodus over.

## 4. Stappen

1. Kopieer de structuur, discoveryvolgorde en rapportvorm uit het PromptManager-command.
2. Vervang iedere projectspecifieke bron en controle door het feitelijke AIMM-pad of verwijder haar als AIMM geen equivalent heeft.
3. Controleer instructies, regels, commands, skills en hun registraties op ontbrekende of conflicterende verwijzingen.
4. Registreer `/audit-config` zonder extra workflowlaag.

## 5. Verificatie

- Draai het command read-only op de AIMM-worktree.
- Bevestig dat AIMM-bronnen worden gelezen en PromptManager-entiteiten nergens als AIMM-feit verschijnen.
- Bevestig dat ontbrekende hostcapaciteit geen `pass` oplevert.
- Run `git diff --check` en `jq empty .claude/settings.json`.

## 6. Klaar wanneer

- `/audit-config` dezelfde nuttige auditkennis als PromptManager bevat, toegespitst op AIMM.
- Het command wijzigt niets en introduceert geen nieuwe skill, scripts of configuratieschema's.
- AC1–AC6 uit de accepted spec zijn in het commandcontract herkenbaar afgedekt.
