# Functionele specificatie: AIMM preflight-workflow

**Feature-map:** `.ai/features/aimm-preflight-workflow/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmpreflight
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-eigenaar krijgt vóór specificatie of implementatie een compact scopebesluit waarin blokkerende keuzes, financiële bewijseisen en runtimegrenzen expliciet zijn gemaakt.

AIMM-taken kunnen productlogica, externe financiële bronnen, dataprovenance en meerdere uitvoeringsomgevingen raken. Wanneer een agent ontbrekende keuzes stil invult, kan een technisch correcte wijziging alsnog onbetrouwbare analyse of onuitvoerbare validatie opleveren.

De preflight onderzoekt eerst beschikbare bronnen, stelt maximaal één richtingbepalende vraag per beurt en eindigt zonder implementatie. Het standaardvraagbudget is vijf vragen; alleen een expliciete keuze verhoogt dit telkens met drie. Kleine eenduidige taken mogen met een gemotiveerde skip direct door.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-opdrachtgever | Beantwoordt productkeuzes en accepteert het scopebesluit | Beslisser over doel en scope |
| Preflight-agent | Onderzoekt bronnen en stelt alleen blokkerende vragen | Alleen-lezen tijdens de preflight |
| AIMM-maintainer | Levert ontbrekende runtime- of operationele context | Adviseur zonder automatisch mutatierecht |

## 3. Scope **[verplicht]**

### In scope

- Triage van een AIMM-taak als triviaal, helder maar risicovol, of ambigu.
- Onderzoek van expliciet genoemde bronnen en relevante repositorycontext vóór vragen worden gesteld.
- Explicitering van scope, niet-scope, beslissingen, aannames, risico’s en open punten.
- Toetsing op provenance, ontbrekende waarden, perioden, eenheden, determinisme en host/runnergrenzen wanneer relevant.
- Een concrete downstream handoff naar analyse, spec, plan, bugrapport of implementatie.

### Niet in scope

- Tijdens de preflight code of configuratie wijzigen — het resultaat is uitsluitend een scopebesluit.
- Vragen stellen die uit beschikbare bronnen beantwoord kunnen worden — research gaat vóór interactie.
- Een volledige technische planning maken — die volgt pas na een stabiel functioneel bereik.
- Iedere kleine taak verplicht interviewen — triviale taken mogen aantoonbaar worden overgeslagen.

## 4. Happy path **[verplicht]**

1. De opdrachtgever levert een ruwe AIMM-taak of verwijst naar bestaand bronmateriaal.
2. De preflight-agent leest de relevante bronnen en onderscheidt feiten, aannames en blokkades.
3. De agent stelt alleen wanneer nodig één vraag met reden, aanbeveling, impact en concrete opties.
4. De opdrachtgever beantwoordt de vraag en de agent verwerkt het besluit zonder opgeloste onderwerpen opnieuw te openen.
5. De preflight produceert een scopebesluit met een bruikbare downstream handoff en voert geen implementatie uit.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De taak is klein en eenduidig | De preflight meldt waarom een interview overbodig is en levert direct een compacte handoff. |
| Bronnen spreken elkaar tegen | De agent toont het conflict en vraagt welke bron leidend is wanneer de instructiehiërarchie geen antwoord geeft. |
| Een financiële bron of periode ontbreekt | De agent markeert de ontbrekende provenance als blokkade en verzint geen waarde of periode. |
| Het vraagbudget is bereikt terwijl een blokkade resteert | De preflight meldt de open blokkade en laat de opdrachtgever kiezen tussen samenvatten, verlengen of stoppen. |
| De opdrachtgever vraagt tussentijds om implementatie | De agent vraagt expliciet of de preflight wordt afgerond of beëindigd voordat muterend werk start. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmpreflight1 — De preflight produceert vóór de eerste vraag een brongebaseerd onderscheid tussen feiten, aannames en blokkerende onbekenden.
- AC-aimmpreflight2 — Iedere vraag bevat reden, aanbevolen richting, impact en twee tot vier concrete keuzes en toont slechts één vraag per beurt.
- AC-aimmpreflight3 — Het scopebesluit bevat doel, in-scope, niet-scope, beslissingen, aannames, risico’s, open punten en downstream handoff.
- AC-aimmpreflight4 — De preflight blokkeert verzonnen financiële waarden, bronattributie, perioden of runtimeclaims.
- AC-aimmpreflight5 — De preflight meldt dat zij read-only eindigt en verandert geen repository-, database- of externe configuratie.
- AC-aimmpreflight6 — De preflight gebruikt standaard maximaal vijf vragen en registreert iedere expliciete budgetverlenging als precies drie aanvullende vragen.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** de start van AIMM-feature-, bugfix-, analyse- en configuratiewerk.
- **Blokkeert:** specgeneratie of implementatie wacht wanneer het scopebesluit nog een materiële productblokkade bevat.
- **Blokkeert-door:** `new-spec`, technische planning en implementatie gebruiken de downstream handoff.
- **Effect op bestaande data:** geen; de preflight is read-only.
- **Effect op bestaande gebruikers:** opdrachtgevers beantwoorden minder maar relevantere vragen; agents vullen minder productkeuzes stil in.
