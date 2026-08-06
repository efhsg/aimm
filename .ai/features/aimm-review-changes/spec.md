# Functionele specificatie: AIMM review-changes optimalisatie

**Feature-map:** `.ai/features/aimm-review-changes/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmrevchanges
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-ontwikkelaar krijgt een evidence-based review van iedere gewijzigde file tegen actuele AIMM-regels, financiële integriteit en uitvoeringsgrenzen vóór finalisatie start.

AIMM heeft al een reviewcommand en skill, maar delen van het huidige contract bevatten PromptManager-conventies die botsen met AIMM, waaronder regels rond type-striktheid en architectuur. Een review die de verkeerde standaard toepast creëert schijnzekerheid.

De optimalisatie behoudt defectdetectie als eerste doel, leest iedere gewijzigde file volledig en scheidt bewezen bevindingen van zaken die aanvullende verificatie vereisen. De review verandert standaard geen bestanden.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-ontwikkelaar | Vraagt de review aan en beslist over herstel | Auteur van de wijziging |
| Code-reviewagent | Onderzoekt diff, volledige files, callers en projectregels | Alleen-lezen in de reviewflow |
| AIMM-domeinreviewer | Beoordeelt bevindingen over financiële betekenis of provenance | Adviseur |

## 3. Scope **[verplicht]**

### In scope

- Review van staged en unstaged wijzigingen binnen de actieve AIMM-worktree.
- Defectdetectie op correctheid, AIMM-codingstandaarden, architectuur, security, tests en documentatie-impact.
- Domeincontrole op provenance, ontbrekende data, perioden, eenheden, determinisme en fail-closed gates.
- Volledige-filelezing, relevante cross-filecontrole, severity, confidence en concrete bronverwijzingen.
- Een vaste blockingpolicy waarin Critical, High en Medium open blijven tot herstel of evidence-based dismissal; Low blijft adviserend.
- Een afzonderlijke, optionele designverfijning nadat blokkerende defecten afwezig zijn.

### Niet in scope

- PromptManager-conventies toepassen wanneer AIMM-regels anders bepalen — AIMM is de doelautoriteit.
- Standaard automatisch bevindingen herstellen — review en implementatie blijven afzonderlijke acties.
- Tests of linters als geslaagd rapporteren zonder geldige AIMM-hostruntime — runnerbeperkingen leiden tot handoff.
- Ongewijzigde legacycode als losstaande bevinding opnemen — alleen impact op de actuele wijziging telt.
- Een blokkerende bevinding op voorkeur of algemene risicoacceptatie sluiten — alleen concreet tegenbewijs kan de classificatie opheffen.

## 4. Happy path **[verplicht]**

1. De ontwikkelaar start de review voor de actuele AIMM-worktree en optionele scope.
2. De reviewer inventariseert de wijzigingen, classificeert het wijzigingstype en selecteert relevante AIMM-controles.
3. De reviewer leest iedere geraakte file volledig en onderzoekt relevante callers, patronen en regels.
4. De review produceert geprioriteerde, bewijsbare bevindingen met severity, confidence en concrete herstelrichting.
5. De ontwikkelaar herstelt blokkerende bevindingen of levert concreet tegenbewijs waarmee de reviewer de bevinding zichtbaar herclassificeert of dismisses.
6. Wanneer geen Critical, High of Medium bevinding resteert, verwijst de review door naar finalisatie of een expliciet gekozen tweede designfase binnen dezelfde reviewflow.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De worktree bevat geen wijzigingen | De review meldt dat er niets te beoordelen is en produceert geen kunstmatig oordeel. |
| Er bestaan onverklaarde wijzigingen buiten de gevraagde scope | De review laat ze ongemoeid en meldt de scopegrens. |
| Een file kan niet volledig worden gelezen | De reviewer geeft geen hoog-confidence oordeel over die file en meldt de verificatieblokkade. |
| Een wijziging raakt externe financiële bronverwerking | De review controleert provenance, onbetrouwbare broninhoud en gedrag bij ontbrekende of conflicterende data. |
| Alleen de PromptManager-runner is beschikbaar | De review voert runner-veilige inspecties uit en draagt AIMM-hostvalidatie exact over. |
| De ontwikkelaar betwist een blokkerende bevinding | De reviewer herleest het relevante bewijs en sluit de bevinding alleen als dat bewijs de oorspronkelijke claim weerlegt. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmrevchanges1 — De review produceert een inventaris van alle binnen scope gewijzigde files en leest iedere beoordeelde file volledig.
- AC-aimmrevchanges2 — Iedere bevinding bevat filelocatie, severity, confidence, AIMM-regel of codebewijs en concrete herstelrichting.
- AC-aimmrevchanges3 — De review weigert PromptManager-regels toe te passen wanneer die conflicteren met canonieke AIMM-regels.
- AC-aimmrevchanges4 — De review bevat voor relevante financiële wijzigingen een expliciete beoordeling van provenance, periode, eenheid, ontbrekende data en fail-closed gedrag.
- AC-aimmrevchanges5 — De review meldt onuitvoerbare hostchecks als handoff en verandert standaard geen repositorybestand.
- AC-aimmrevchanges6 — De review blokkeert finalisatie zolang een Critical, High of Medium bevinding openstaat en registreert bij dismissal het concrete tegenbewijs.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** de bestaande AIMM `review-changes`-flow en de overgang naar `finalize-changes`.
- **Blokkeert:** finalisatie wacht tot iedere Critical, High en Medium bevinding is hersteld of op basis van concreet tegenbewijs is dismissed.
- **Blokkeert-door:** finalisatie gebruikt scope en bevindingen om de juiste validatie en handoff te bepalen.
- **Effect op bestaande data:** geen; de review is standaard read-only.
- **Effect op bestaande gebruikers:** ontwikkelaars krijgen minder fout-positieven uit verkeerde projectconventies en beter onderbouwde defectmeldingen.

## 8. Severity- en blockingcontract **[optioneel]**

| Severity | Betekenis | Gevolg |
|----------|-----------|--------|
| Critical | Securitylek, datacorruptie, gefabriceerde financiële data of doorbroken provenance-/ownershipgrens | Blokkeert altijd; alleen dismissal met bewijs dat de claim feitelijk onjuist is. |
| High | Functioneel defect, data-integriteitsfout of materiële architectuurschending | Blokkeert tot herstel of evidence-based dismissal. |
| Medium | Ontbrekende vereiste test, contractschending of relevante standaardafwijking | Blokkeert tot herstel of evidence-based dismissal. |
| Low | Niet-blokkerende verfijning van leesbaarheid, naamgeving of documentatie | Adviserend; mag als zichtbaar open punt doorgaan. |
