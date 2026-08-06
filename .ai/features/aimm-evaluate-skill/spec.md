# Functionele specificatie: AIMM skill-evaluatie

**Feature-map:** `.ai/features/aimm-evaluate-skill/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmevalskill
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-configuratiebeheerder kan iedere bestaande of externe kandidaat-skill evidence-based beoordelen op semantische doelbereiking en, waar relevant, aanvullende AIMM-transferfit.

Een structureel volledige skill bereikt niet automatisch haar doel. Daarnaast is een technisch kopieerbare PromptManager-skill niet automatisch geschikt voor AIMM: verschillen in architectuur, financiële provenance, testuitvoering en runnercapaciteiten kunnen een ogenschijnlijk bruikbaar contract onveilig of misleidend maken.

De standaardmodus beoordeelt semantische kwaliteit van iedere AIMM-skill. Voor een externe kandidaat voegt transfermodus AIMM-fit toe en levert zij daarnaast een advies om over te nemen, aan te passen of af te wijzen. Geen van beide modi verandert een bron- of doelbestand.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-configuratiebeheerder | Selecteert de kandidaat en beslist over het advies | Eigenaar en beslisser |
| Skill-evaluator | Vergelijkt het contract met AIMM-bronnen en feitelijk gedrag | Alleen-lezen |
| AIMM-maintainer | Bevestigt runtime- of domeinaannames die niet lokaal bewijsbaar zijn | Adviseur |

## 3. Scope **[verplicht]**

### In scope

- Evaluatie van één AIMM-skill of externe kandidaat-skill en de bijbehorende command-wrapper wanneer die bestaat.
- Toetsing van doel, algoritme, stopmomenten, output, afhankelijkheden en Definition of Done.
- Standaardmodus met peer-vergelijking, een rubric passend bij het skillgebied en open beoordeling, ieder met expliciete confidence.
- Transfermodus met aanvullende vergelijking tegen AIMM-regels, projectstructuur, runtimegrenzen en financiële provenance-eisen.
- Een semantische uitkomst `uitgelijnd`, `gedeeltelijk uitgelijnd` of `niet uitgelijnd` en in transfermodus aanvullend `overnemen`, `aanpassen` of `afwijzen`.

### Niet in scope

- De kandidaat-skill direct wijzigen — evaluatie en optimalisatie blijven gescheiden beslissingen.
- Een volledig skillportfolio auditen — dat is de verantwoordelijkheid van de ecosysteemaudit.
- Een ontbrekende AIMM-behoefte verzinnen — onbewezen waarde leidt tot afwijzen of parkeren.
- PromptManager-projectregels als AIMM-regels behandelen — AIMM blijft de doelautoriteit.

## 4. Happy path **[verplicht]**

1. De beheerder wijst één skill, eventueel haar command-wrapper en standaard- of transfermodus aan.
2. De evaluator leest het volledige contract, de directe afhankelijkheden, vergelijkbare AIMM-skills en relevante AIMM-bronnen.
3. De evaluator beoordeelt peer-afwijkingen, het passende skillrubric en domeinrisico’s met afzonderlijke confidence.
4. In transfermodus vergelijkt de evaluator aanvullend de kandidaat met AIMM’s feitelijke workflow en uitvoeringsomgeving.
5. De evaluatie produceert één semantische uitkomst en in transfermodus één onderbouwd hergebruikadvies.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De kandidaat heeft geen command-wrapper | De evaluatie beoordeelt de skill zelfstandig en vermeldt dat commandrouting niet van toepassing is. |
| Er bestaan minder dan twee vergelijkbare AIMM-skills | De evaluatie meldt dat peer-vergelijking onvoldoende corpus heeft en baseert daarop geen high-confidence bevinding. |
| Een afhankelijkheid ontbreekt in AIMM | De evaluatie meldt de ontbrekende grens en adviseert aanpassen of afwijzen zonder de afhankelijkheid te fabriceren. |
| Het contract conflicteert met een AIMM-regel | De AIMM-regel krijgt voorrang en het conflict wordt als materiële aanpassing geregistreerd. |
| De gebruiksfrequentie of pijn is onbewezen | De evaluatie verlaagt de prioriteit en markeert de waarde als aanname. |
| Alleen PromptManager-specifieke productconcepten dragen de skill | De evaluatie adviseert afwijzen tenzij een concrete AIMM-equivalent aantoonbaar bestaat. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmevalskill1 — De standaardevaluatie bevat peer-vergelijking, rubricbeoordeling en open semantische beoordeling met bewijs en confidence per dimensie.
- AC-aimmevalskill2 — De evaluatie toont iedere materiële afhankelijkheid en vermeldt hoe die de semantische doelbereiking beïnvloedt.
- AC-aimmevalskill3 — Iedere evaluatie levert precies één semantische uitkomst `uitgelijnd`, `gedeeltelijk uitgelijnd` of `niet uitgelijnd`; transfermodus levert daarnaast precies één advies `overnemen`, `aanpassen` of `afwijzen`.
- AC-aimmevalskill4 — De evaluatie weigert PromptManager-regels boven conflicterende AIMM-regels te plaatsen.
- AC-aimmevalskill5 — De evaluatie meldt dat zij read-only is en verandert geen command-, skill-, index- of routingbestand.
- AC-aimmevalskill6 — Onvoldoende peercorpus is zichtbaar als ontbrekende vergelijkingsdimensie en produceert geen high-confidence peerbevinding.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** semantische kwaliteitscontrole van AIMM-skills en selectie van herbruikbare command/skill-combinaties.
- **Blokkeert:** een transferspec wacht op het advies `overnemen` of `aanpassen`; een optimalisatiespec voor een AIMM-eigen skill wacht op een semantische uitkomst `gedeeltelijk uitgelijnd` of `niet uitgelijnd` met concrete bevindingen.
- **Blokkeert-door:** skilloptimalisatie en de uiteindelijke ecosysteemaudit gebruiken de evaluatiebevindingen.
- **Effect op bestaande data:** geen; de evaluatie is read-only en advisory.
- **Effect op bestaande gebruikers:** de configuratiebeheerder krijgt minder ruis en vermijdt kostbare overname van niet-passende workflows.
