# Functionele specificatie: AIMM skill-ecosysteemaudit

**Feature-map:** `.ai/features/aimm-audit-skills/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmskillaudit
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-repository-eigenaar krijgt na iedere invoeringsgolf één samenhangende audit van skillregistratie, commandrouting, afhankelijkheden, overlap en veiligheidscontracten.

Losse skills kunnen afzonderlijk bruikbaar lijken terwijl hun combinatie dubbele verantwoordelijkheden, cyclische afhankelijkheden of onbereikbare workflows veroorzaakt. De pilot heeft daarom naast evaluatie per skill ook een systeembrede eindgate nodig.

De standaardaudit is read-only en rapporteert zowel lokale defecten als terugkerende patronen. De skillindex bepaalt welke skills productiescope hebben; bestanden op disk en command-wrappers leveren aanvullende driftsignalen. Wijzigingen volgen pas na een afzonderlijk scopebesluit.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-repository-eigenaar | Start de audit en prioriteert herstelwerk | Eigenaar en beslisser |
| Skill-auditor | Inventariseert en beoordeelt het volledige ecosysteem | Alleen-lezen in standaardmodus |
| AI-codingagent | Gebruikt geregistreerde routing tijdens normale taken | Consument van de gecontroleerde configuratie |

## 3. Scope **[verplicht]**

### In scope

- Alle skills die in de AIMM-skillindex zijn geregistreerd, hun command-wrappers en directe routingregels.
- Vergelijking van de canonieke index met alle skillbestanden op disk en alle command-wrappers als afzonderlijke driftcontrole.
- Controle op ontbrekende bestanden, verweesde registraties, foutieve afhankelijkheden en wrapper/skill-drift.
- Detectie van overlappende taken, tegenstrijdige stopmomenten en onveilige mutatiecontracten.
- Rapportage per bevinding en van ecosysteembrede patronen.

### Niet in scope

- Bevindingen standaard automatisch herstellen — audit en mutatie vereisen verschillende autorisatie.
- Een disk-only skill semantisch als productieskill beoordelen — het bestand blijft wel zichtbaar als `disk-only` driftsignaal totdat de eigenaar registratie of bewuste verwijdering beslist.
- Productcodekwaliteit beoordelen — daarvoor bestaat de code-reviewflow.
- Kwantiteit als kwaliteitsmaat gebruiken — meer skills is geen zelfstandig doel.

## 4. Happy path **[verplicht]**

1. De eigenaar start de ecosysteemaudit na de invoering van geselecteerde pilotcombinaties.
2. De auditor bouwt vanuit de skillindex de canonieke scope en vergelijkt die afzonderlijk met skills op disk en command-wrappers.
3. De auditor controleert ieder item lokaal en vergelijkt vervolgens verantwoordelijkheden over het hele ecosysteem.
4. De audit produceert een rapport met individuele bevindingen, systeemrisico’s en prioriteiten.
5. De eigenaar kiest afzonderlijk welke bevindingen in een volgende wijzigingsscope worden opgelost.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Een command verwijst naar een ontbrekende skill | De audit meldt dat de command niet betrouwbaar uitvoerbaar is en markeert de registratie als defect. |
| Twee skills claimen dezelfde primaire taak | De audit toont de overlap en adviseert één eigenaar of een expliciete routeringsgrens. |
| Een afhankelijkheid vormt een cyclus | De audit registreert de cyclus en blokkeert een advies dat de cyclus verder uitbreidt. |
| Een muterende skill mist een autorisatie- of herstelgrens | De audit classificeert dit als veiligheidsbevinding en vermeldt de geraakte workflow. |
| Een skill bestaat op disk maar ontbreekt in de index | De audit meldt `disk-only` als registrydrift en neemt de skill niet stilzwijgend op in de semantische productiescan. |
| Een command-wrapper verwijst naar een niet-geregistreerde of ontbrekende skill | De audit meldt `wrapper-only` met beide aangetroffen bronlocaties. |
| De audit treft een bestaande gebruikerswijziging aan | De audit blijft read-only en schrijft de bevinding niet toe aan de pilot zonder bewijs. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmskillaudit1 — De audit produceert aantallen en namen van index-geregistreerde skills, disk-only skills, wrappers en directe afhankelijkheden als afzonderlijke verzamelingen.
- AC-aimmskillaudit2 — De audit meldt `index-zonder-bestand`, `disk-only`, `wrapper-only` en conflicterende registraties met concrete bronverwijzingen.
- AC-aimmskillaudit3 — Iedere systeembevinding bevat impact, urgentie en een afgebakende aanbevolen vervolgstap.
- AC-aimmskillaudit4 — De standaardaudit weigert bestanden te wijzigen en vermeldt dat herstel afzonderlijke goedkeuring vereist.
- AC-aimmskillaudit5 — Een herhaalde audit toont welke bevindingen sinds de vorige meetlat zijn opgelost, gebleven of ontstaan.
- AC-aimmskillaudit6 — Het rapport bevat standaard in de respons de classificaties `kritiek`, `waarschuwing` en `informatie` en vermeldt dat duurzame opslag alleen na een afzonderlijk verzoek plaatsvindt.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** AIMM-skillindex, commanddiscovery, topicrouting en de governance van toekomstige skilluitbreidingen.
- **Blokkeert:** de pilot kan niet als afgerond gelden zonder een ecosysteemaudit van de ingevoerde combinaties.
- **Blokkeert-door:** vervolggolven voor reviewtriage, skillbeheer en documentatie gebruiken dit rapport als baseline.
- **Effect op bestaande data:** geen; de standaardaudit leest uitsluitend configuratiebestanden.
- **Effect op bestaande gebruikers:** agents krijgen na herstel voorspelbaardere routing; de audit zelf verandert hun gedrag niet.
