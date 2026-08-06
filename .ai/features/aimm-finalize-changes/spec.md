# Functionele specificatie: AIMM finalize-changes optimalisatie

**Feature-map:** `.ai/features/aimm-finalize-changes/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmfinalize
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-ontwikkelaar krijgt één fail-closed finalisatieflow die scope, regels, specs, validatie, staging en maintainerhandoff aantoonbaar afrondt zonder commit of push uit te voeren.

AIMM heeft al een omgevingbewuste finalisatiecommand die gerichte staging en hosthandoff ondersteunt. De nieuwe spec- en governanceketen voegt echter voorwaarden toe: productimplementatie moet aantoonbaar aan een `accepted` spec zijn gekoppeld, mechanische specfouten mogen niet worden genegeerd en runnerchecks mogen niet als vervanging voor hostvalidatie gelden.

De optimalisatie behoudt AIMM’s strikte mutatiegrenzen. Alleen goedgekeurde paden worden gestaged; commit en push blijven afzonderlijke, expliciet gevraagde acties.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-ontwikkelaar | Start finalisatie en bevestigt de goedgekeurde scope | Auteur en beslisser |
| Finalisatie-agent | Controleert, valideert en stageert uitsluitend goedgekeurde paden | Uitvoerder binnen de worktree |
| AIMM-maintainer | Voert hostchecks uit die de PromptManager-runner niet ondersteunt | Uitvoerder van de handoff |

## 3. Scope **[verplicht]**

### In scope

- Vaststelling van actieve worktree, branch, wijzigingsscope en onverklaarde bestaande wijzigingen.
- Controle van gewijzigde files tegen AIMM-regels en koppeling aan relevante feature- of bugfixartefacten.
- Vaststelling of de wijziging productgedrag, alleen documentatie/configuratie, een bugfix of een gedragloze refactor betreft en welk artefactcontract daarbij hoort.
- Mechanische validatie van gewijzigde specs en controle van documentatie-impact.
- Uitvoering van ondersteunde runner-veilige checks en exacte handoff voor AIMM-hostvalidatie.
- Gerichte staging van uitsluitend goedgekeurde paden en voorstel voor een AIMM-conforme commitboodschap.

### Niet in scope

- Automatisch committen of pushen — beide vereisen een afzonderlijk expliciet verzoek.
- Alle worktreewijzigingen breed stagen — unrelated werk blijft onaangeraakt.
- Een mislukte of onuitvoerbare check als geslaagd behandelen — de flow faalt of levert maintainerhandoff.
- Ontwerp- of implementatieartefacten automatisch archiveren — archivering vereist een afzonderlijk besluit.
- Productcode finaliseren met een ontbrekende, ambigue, `draft` of `in-review` feature-spec — alleen een eenduidig gekoppelde `accepted` spec autoriseert feature-implementatie.

## 4. Happy path **[verplicht]**

1. De ontwikkelaar bevestigt de actieve AIMM-worktree en de paden die tot de wijziging behoren.
2. De finalisatie-agent classificeert het wijzigingstype en koppelt feature-implementatie via een expliciete featurehint, geraakte feature-map of eenduidige branchrelatie aan precies één duurzame spec.
3. De agent leest de volledige wijzigingsset, relevante regels en duurzame taakartefacten en verifieert dat feature-implementatie een mechanisch geldige `accepted` spec heeft.
4. De agent controleert regels en specs en voert alle in de actieve omgeving ondersteunde validaties uit.
5. Wanneer een toepasselijke blokkerende hostcheck niet uitvoerbaar is, levert de agent exacte maintainercommando’s en stopt vóór staging en commitvoorstel totdat bewijs is teruggeleverd.
6. Na volledig geslaagde toepasselijke validatie stageert de agent uitsluitend de goedgekeurde paden en levert een commitvoorstel zonder commit of push.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Een gewijzigd pad valt buiten de goedgekeurde scope | De finalisatie stopt vóór staging en meldt het afwijkende pad. |
| Een relevante spec faalt de mechanische gate | De finalisatie blokkeert en verwijst naar de concrete specbevindingen. |
| De runner mist Docker of de vereiste PHP-runtime | De flow levert exacte hostcommando’s en claimt geen geslaagde hostvalidatie. |
| Een ondersteunde validatie faalt | De finalisatie stopt vóór staging en rapporteert de volledige foutstatus. |
| Er staan al unrelated files staged | De flow wijzigt die staging niet en stopt wanneer de goedgekeurde staged scope niet eenduidig kan worden bewezen. |
| Productcode koppelt aan nul of meerdere feature-specs | De finalisatie blokkeert en vraagt één expliciete featurekoppeling. |
| Alleen een nieuwe of gewijzigde spec is in scope | De finalisatie valideert en mag de `draft`- of `in-review`-spec als documentatiewerk voorbereiden zonder daarmee productimplementatie te autoriseren. |
| Een gedragloze refactor of bugfix heeft geen feature-spec | De finalisatie gebruikt het toepasselijke AIMM-documentatiepad en vereist geen kunstmatige feature-spec. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmfinalize1 — De finalisatie produceert vóór mutatie een overzicht van actieve root, branch, goedgekeurde paden en afwijkende worktreewijzigingen.
- AC-aimmfinalize2 — Iedere gewijzigde AIMM-spec passeert de mechanische specgate voordat finalisatie verdergaat.
- AC-aimmfinalize3 — De flow meldt iedere validatie als `pass`, `fail` of `maintainer handoff required`, weigert een ontbrekende check als `pass` te registreren en stopt bij een blokkerende handoff vóór staging.
- AC-aimmfinalize4 — De staged diff bevat uitsluitend goedgekeurde paden en wordt volledig teruggelezen voordat een commitvoorstel verschijnt.
- AC-aimmfinalize5 — De finalisatie levert een AIMM-conforme commitboodschap en voert geen commit of push uit.
- AC-aimmfinalize6 — Feature-productcode blokkeert tenzij precies één gekoppelde spec mechanisch valide is en status `accepted` heeft.
- AC-aimmfinalize7 — Document-only specwerk mag een mechanisch geldige `draft`- of `in-review`-spec voorbereiden maar vermeldt expliciet dat die status geen productimplementatie autoriseert.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** de bestaande AIMM-finalisatiecommand, specvalidatie, staging en maintainerhandoff.
- **Blokkeert:** commitvoorbereiding wacht op een schone scopeset, het juiste duurzame artefactcontract, geldige specs en `pass` voor alle toepasselijke blokkerende validaties.
- **Blokkeert-door:** de afzonderlijke commit/push-flow gebruikt de teruggelezen staged diff en commitboodschap.
- **Effect op bestaande data:** alleen de Git-index van goedgekeurde paden kan wijzigen; product- en financiële data blijven onaangeraakt.
- **Effect op bestaande gebruikers:** ontwikkelaars krijgen één controleerbare opleveringsstatus zonder schijnsucces in de runner.

## 8. Finalisatiematrix **[optioneel]**

| Wijzigingstype | Vereist artefact | Specstatus | Hosthandoff |
|----------------|------------------|------------|-------------|
| Feature-productimplementatie | Precies één gekoppelde feature-spec | `accepted` en mechanisch geldig | Iedere toepasselijke blokkerende check moet als `pass` worden teruggeleverd vóór staging. |
| Alleen featuredocumentatie | De gewijzigde feature-spec | `draft`, `in-review` of `accepted`, passend bij inhoud | Alleen documentatiechecks zijn toepasselijk; productruntimechecks zijn niet automatisch vereist. |
| Bugfix | Bugrapport wanneer AIMM’s documentatiepad dat vereist | Niet van toepassing | Relevante productchecks moeten als `pass` worden teruggeleverd. |
| Gedragloze refactor | Geen feature-spec vereist; taakscope blijft aantoonbaar | Niet van toepassing | Relevante structurele checks moeten als `pass` worden teruggeleverd. |
| Alleen agentconfiguratie | Goedgekeurde configuratiescope en eventuele config-spec | Volgens het geraakte artefact | Runner-veilige checks moeten passeren; hostcheck alleen wanneer de configuratie hostgedrag verandert. |
