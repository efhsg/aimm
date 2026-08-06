# Functionele specificatie: AIMM configuratie-audit

**Feature-map:** `.ai/features/aimm-audit-config/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmcfgaudit
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-repository-eigenaar krijgt vóór en na configuratiewijzigingen een herhaalbare audit die ontbrekende, verouderde en conflicterende agentinstructies zichtbaar maakt.

AIMM heeft één canonieke instructiebron met aanvullende regels, projectconfiguratie, commands en skills. Nieuwe combinaties vergroten het risico op dode verwijzingen, dubbele beleidsregels en gedrag dat niet uitvoerbaar is in zowel de hostomgeving als de PromptManager-runner.

De audit is een governancegate: zij levert bewijs en prioriteiten, maar verandert de configuratie niet. Het rapport verschijnt standaard in de respons en wordt alleen op expliciet verzoek als duurzaam artefact opgeslagen. Daardoor kan dezelfde nulmeting na iedere invoeringsgolf opnieuw worden gebruikt.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-repository-eigenaar | Start de audit en beslist over vervolgacties | Eigenaar van de AIMM-configuratie |
| AI-codingagent | Leest de configuratiebronnen en stelt het auditrapport op | Alleen-lezen tijdens de audit |
| AIMM-maintainer | Beoordeelt bevindingen over hostafhankelijke validatie | Lezer en uitvoerder van expliciete handoffs |

## 3. Scope **[verplicht]**

### In scope

- Controle van de canonieke instructieketen, regels, projectconfiguratie, commandregister en skillregister.
- Detectie van ontbrekende verwijzingen, dubbele verantwoordelijkheden, tegenstrijdige regels en niet-uitvoerbare runtimeclaims.
- Classificatie van bevindingen op impact, bewijs en aanbevolen vervolgactie.
- Een vergelijkbare nul- en eindmeting voor de AIMM command/skill-pilot.
- Registry-pariteit op hoofdniveau: bestaan commands, skills en indexverwijzingen aantoonbaar en verwijzen zij naar geldige doelen.

### Niet in scope

- Configuratiebestanden automatisch aanpassen — de eigenaar moet iedere mutatie afzonderlijk goedkeuren.
- Productcode of financiële data beoordelen — daarvoor bestaan code- en domeinreviews.
- Individuele skillalgoritmen of ecosysteempatronen semantisch beoordelen — `evaluate-skill` en `audit-skills` zijn daarvan eigenaar.
- Machinebrede gebruikersconfiguratie normaliseren — die valt buiten de AIMM-repository.
- Hostvalidatie als geslaagd rapporteren wanneer alleen de runner beschikbaar is — onuitvoerbare checks worden overgedragen.

## 4. Happy path **[verplicht]**

1. De eigenaar start de configuratie-audit voor de actuele AIMM-worktree.
2. De agent inventariseert alle canonieke configuratiebronnen, registraties en onderlinge verwijzingen.
3. De agent vergelijkt iedere claim met de feitelijke repositorystructuur en beschikbare uitvoeringsomgeving.
4. De audit produceert een geprioriteerd rapport met bewijs, impact, urgentie en aanbevolen actie per bevinding en verwijst gedetailleerde skillbevindingen naar de skill-evaluatie of ecosysteemaudit.
5. De eigenaar gebruikt dezelfde audit na de pilot om resterende of nieuw ontstane afwijkingen vast te stellen.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Een verwezen bestand ontbreekt | De audit meldt de verbroken verwijzing en welke workflow daardoor niet betrouwbaar uitvoerbaar is. |
| Twee bronnen geven tegengestelde instructies | De audit toont beide bronnen en gebruikt de vastgelegde instructiehiërarchie zonder stilzwijgende keuze. |
| Een controle vereist de AIMM-hostruntime | De audit registreert de controle als maintainerhandoff en claimt geen geslaagd resultaat. |
| De worktree bevat onverklaarde wijzigingen | De audit meldt de afwijkende uitgangssituatie en scheidt bestaande wijzigingen van auditbevindingen. |
| Een bron bevat een credential of gevoelige waarde | De audit vermeldt alleen de categorie en locatie en neemt de waarde niet over in output. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmcfgaudit1 — De audit produceert een inventaris van canonieke instructies, regels, commands, skills en registraties met hun onderlinge verwijzingen.
- AC-aimmcfgaudit2 — Iedere bevinding bevat bewijs, impact, urgentie en een aanbevolen vervolgactie.
- AC-aimmcfgaudit3 — De audit meldt hostafhankelijke controles als handoff en weigert ze als geslaagd te registreren wanneer de vereiste runtime ontbreekt.
- AC-aimmcfgaudit4 — De audit toont conflicten volgens de AIMM-instructiehiërarchie en verandert geen repositorybestand.
- AC-aimmcfgaudit5 — Een herhaalde audit produceert een vergelijkbare eindmeting waarin opgeloste, resterende en nieuwe bevindingen afzonderlijk zichtbaar zijn.
- AC-aimmcfgaudit6 — Het rapport classificeert iedere bevinding als `kritiek`, `waarschuwing` of `informatie`, toont registry-pariteit slechts eenmaal en verwijst semantische skillbevindingen naar `evaluate-skill` of `audit-skills`.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** AIMM-agentconfiguratie, command- en skillregistratie en de invoeringsflow van de command/skill-pilot.
- **Blokkeert:** de eerste pilotimplementatie wacht op een vastgelegde nulmeting van kritieke configuratieconflicten.
- **Blokkeert-door:** de eindevaluatie van de pilot gebruikt deze audit als sluitende governancegate.
- **Effect op bestaande data:** geen; de audit leest repositoryconfiguratie en schrijft uitsluitend een rapport wanneer de gebruiker dat vraagt.
- **Effect op bestaande gebruikers:** de repository-eigenaar krijgt aantoonbare configuratiekwaliteit; normale AIMM-productgebruikers merken geen gedragswijziging.
