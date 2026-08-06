# Implementatieplan: AIMM skill-ecosysteemaudit

**Feature-map:** `.ai/features/aimm-audit-skills/`
**Status:** draft
**Aangemaakt:** 2026-08-06
**Spec:** `spec.md` (`accepted`, `AIMM-PRD-1`)

## 1. Doel

Poort de read-only rapportkennis uit de PromptManager-combinatie `/audit-skills` naar AIMM en laat de muterende `fix-sweep` weg.

## 2. Bron en doelbestanden

Bron:

- `/var/www/worktree/promptmanager/.claude/commands/audit-skills.md`
- `/var/www/worktree/promptmanager/.claude/skills/audit-skills.md`

Doel:

```text
.claude/commands/audit-skills.md        (nieuw: dunne wrapper)
.claude/skills/audit-skills.md          (nieuw: AIMM-skill)
.claude/skills/index.md                 (registratie)
CLAUDE.md                              (slash-command vermelden)
```

## 3. AIMM-aanpassingen

- Behoud index-, disk- en wrapperinventarisatie, overlapdetectie en een compact ecosysteemrapport.
- Gebruik de AIMM-skillindex als scope en de feitelijke AIMM-bestanden als driftbewijs.
- Beoordeel eenvoudige afhankelijkheidsverwijzingen zoals ze in de skills staan; introduceer geen nieuw metadataschema.
- Laat subagents, versiecontracten, scoring, fix-sweep en automatische mutatie weg.

## 4. Stappen

1. Poort de wrapper uitsluitend voor read-only rapportmodus.
2. Poort de inventarisatie en compacte controles op ontbrekende registratie, overlap, conflicterende stops en onveilige mutatie-instructies.
3. Gebruik waar nuttig de beoordelingsvragen uit `/evaluate-skill`, zonder nieuw koppelformaat.
4. Registreer command en skill.

## 5. Verificatie

- Geïndexeerde skill zonder bestand wordt gemeld.
- Disk-only skill en wrapper zonder skill worden afzonderlijk gemeld.
- Twee skills met dezelfde hoofdtaak leveren één overlapbevinding.
- De audit blijft read-only en biedt geen herstelmodus aan.
- Een tweede run op dezelfde inhoud levert dezelfde geordende inventaris.

## 6. Klaar wanneer

- AIMM de nuttige ecosysteemauditkennis uit PromptManager heeft in een eenvoudige read-only combinatie.
- Wrapper, skill en registratie zijn de enige wijzigingen.
- AC1–AC6 uit de accepted spec zijn in het skillcontract herkenbaar afgedekt.
