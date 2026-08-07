# Functionele specificatie: stock-analyzer

**Feature-map:** `.ai/features/stock-analyzer/`
**Status:** draft  <!-- toegestaan: draft | in-review | accepted -->
**Feature-slug:** stock-analyzer  <!-- korte afkorting voor AC-prefix, bv. "multimodel", "scaffold" -->
**Aangemaakt:** 2026-07-30  <!-- eenmalig bij generatie; voor updates is git-log leidend, niet dit veld -->

<!--
=============================================================
PRD-CONTRACT
=============================================================
Dit document beschrijft WAT en WAAROM, niet HOE.

Verboden in dit document (implementatie-grens):
  - Klasse-, functie- of methode-namen
  - SQL-schema, CREATE/INSERT/UPDATE, migratie-syntax
  - Library-imports, framework-specifieke syntax, config-keys (.env)
  - Concrete endpoints of URL-paden, tenzij extern contract
  - Packagenamen, versies, "composer require ..."

Toegestaan (grens-verwijzingen):
  - Verwijzingen naar bestaande entiteit- of service-namen als grensaanduiding
    (bv. "consistent met AiRun-eigenaarschap"), zolang het geen implementatiekeuze
    prescribt.

Implementatie-detail hoort in `plan.md` naast dit bestand.
-->

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** Een lokaal draaiende stock analyzer geeft de belegger per ticker een 8 Pillars-gezondheidsoordeel en een interactieve DCF-waardering, zodat het $70/maand-abonnement op Everything Money vervalt en formules én data in eigen beheer komen.

De belegger gebruikt vandaag Everything Money voor twee taken: een snelle financiële gezondheidscheck (8 Pillars over 5–10 jaar historie) en het bepalen van een maximale koopprijs via Discounted Cash Flow-scenario's. Beide taken draaien op openbare financiële data en standaard formules; het abonnement betaalt vooral voor verpakking. Everything Money is de functionele referentie: dit product biedt dezelfde kernfunctionaliteit, maar lokaal en in eigen beheer.

De strategische intake stelde empirisch vast dat gratis Yahoo Finance-data slechts vier boekjaren fundamentals levert en daarmee afvalt. Financial Modeling Prep (FMP) is gekozen als externe dataprovider; de bestaande FMP-integratie in aimm dient als gevalideerde kennisbron (veldmappings, quota-omgang, testdata). Opgehaalde jaarcijfers blijven blijvend lokaal beschikbaar, zodat raadplegingen niet afhangen van provider-limieten of latere tariefwijzigingen. Het product is een zelfstandige applicatie naast aimm.

---

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| Belegger | Voert tickers in, beoordeelt het Pillars-dashboard, stelt DCF-scenario's in, start een eenmalige backfill-run | Eigenaar (enige gebruiker, lokale applicatie) |
| FMP (externe dataprovider) | Levert jaarrekeningen, kengetallen en actuele koersen via een extern API-contract | Extern systeem; geen toegang tot lokale data |

---

## 3. Scope **[verplicht]**

### In scope

- Ticker-invoer (Amerikaanse en internationale tickers die FMP ondersteunt) met direct 8 Pillars-oordeel over minimaal 5 jaar historie.
- Per pillar: pass/fail-status, de onderliggende waarde(n) en de gehanteerde drempel, conform de pijlertabel hieronder.
- Drempels zijn per pillar aanpasbaar zonder aparte beheer-UI; een aangepaste drempel geldt bij de eerstvolgende beoordeling en het dashboard toont altijd de gehanteerde waarde.
- Interactieve DCF-calculator met drie scenario's (laag/midden/hoog); per scenario instelbare kasstroomgroei, eindwaarde-multiplier en verdisconteringsvoet, met directe herberekening van de Fair Value per aandeel.
- Blijvende lokale opslag van afgesloten boekjaren en kwartalen; vluchtige gegevens (koers, TTM-cijfers) met vervaltermijn.
- Geforceerde verversing per ticker die de opgeslagen historie vervangt, voor herziene of gecorrigeerde cijfers.
- Eenmalige backfill-run die per ticker uit een lijst de volledige beschikbare historie ophaalt, herstartbaar zonder dubbele aanvragen.
- Historiegrafieken voor omzet, winst en vrije kasstroom.
- Lokaal gebruik door één gebruiker zonder authenticatie.

### De acht pijlers

De pijlerdefinities volgen de canonieke Everything Money-set (zie OP-stock-analyzer-D voor verificatie):

| # | Pijler | Meting | Pass-conditie (default) |
|---|--------|--------|-------------------------|
| 1 | Waardering — P/E | Gemiddelde koers/winst-verhouding over 5 jaar | Lager dan 22,5 |
| 2 | Rendement — ROIC | Gemiddeld rendement op geïnvesteerd vermogen over 5 jaar | Hoger dan 9% |
| 3 | Omzetgroei | Omzet nu ten opzichte van 5 jaar geleden | Gegroeid |
| 4 | Winstgroei | Nettowinst nu ten opzichte van 5 jaar geleden | Gegroeid |
| 5 | Aandeleninkoop | Aantal uitstaande aandelen nu ten opzichte van 5 jaar geleden | Gedaald |
| 6 | Schuldaflossing | Langlopende verplichtingen ten opzichte van de totale vrije kasstroom van de afgelopen 5 jaar | Verplichtingen zijn lager (aflosbaar binnen 5 jaar kasstroom) |
| 7 | Waardering — P/FCF | Gemiddelde koers ten opzichte van vrije kasstroom over 5 jaar | Lager dan 22,5 |
| 8 | Kasstroomgroei | Vrije kasstroom nu ten opzichte van 5 jaar geleden | Gegroeid |

**Extra indicator (geen pijler):** current ratio — kortlopende bezittingen ten opzichte van kortlopende schulden, signaalwaarde bij 1 of lager. Wordt naast de acht pijlers getoond, zichtbaar gescheiden, en telt niet mee in de pijlerscore. Deze indicator behoudt de liquiditeitscheck uit de oorspronkelijke architectuurbrief zonder de vergelijkbaarheid met de Everything Money-referentie te breken.

### Niet in scope

- Wijzigingen aan aimm — aimm blijft ongemoeide referentie; verstrengeling van twee codebases zou beide producten vertragen.
- Alternatieve databronnen zoals Yahoo Finance of SEC EDGAR — Yahoo is empirisch te ondiep (vier boekjaren), EDGAR vergt onevenredig veel mappingwerk naast een werkende FMP-route.
- Publieke deployment, authenticatie en multi-user — persoonlijk gereedschap; hosting voegt secrets-, compliance- en beveiligingslasten toe zonder gebruikerswaarde.
- Watchlists, alerts, BUY/HOLD/SELL-advies en PDF-rapporten — dat is aimm-territorium; deze grens voorkomt feature creep.
- Beheer-UI voor de lokale databuffer — de buffer is onzichtbare infrastructuur; een beheerscherm voegt schijncomplexiteit toe.
- Automatische periodieke dataverversing — verversing gebeurt bij raadpleging of expliciet per ticker; een achtergrondplanner is voor één gebruiker overbodig.
- Marktbrede Pillar-screener en portfolio-functies zoals Everything Money die biedt — vergt bulkdata over duizenden tickers en staat los van de per-ticker-taak; kandidaat voor een latere iteratie zodra de basis staat.

---

## 4. Happy path **[verplicht]**

1. De belegger voert een tickersymbool in (bijvoorbeeld AAPL).
2. Het systeem controleert de lokale buffer, haalt alleen ontbrekende historie op bij FMP en slaat afgesloten periodes blijvend op.
3. Het systeem toont het 8 Pillars-dashboard: per pillar pass/fail, de onderliggende waarde(n) en de gehanteerde drempel, plus historiegrafieken voor omzet, winst en vrije kasstroom.
4. De belegger opent de DCF-calculator; het systeem vult de startwaarden (actuele koers, TTM vrije kasstroom, aantal uitstaande aandelen) uit de eerder opgehaalde en actuele gegevens.
5. De belegger stelt per scenario (laag/midden/hoog) de kasstroomgroei, de eindwaarde-multiplier en de verdisconteringsvoet in.
6. Het systeem herrekent bij elke aanpassing direct de Fair Value per aandeel en toont per scenario de marge ten opzichte van de actuele koers.
7. Bij een latere raadpleging van dezelfde ticker levert het systeem de historie uit de lokale buffer en ververst het alleen vluchtige gegevens.

---

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Onbekend of foutief tickersymbool | Systeem meldt "ticker niet gevonden" en toont geen leeg dashboard |
| Minder dan 5 jaar historie beschikbaar (bijv. recente beursgang) | Betrokken pillar toont status "onvoldoende data" en telt niet mee als fail |
| FMP levert een onvolledig overzichtstype (bijv. wél resultatenrekening, géén kasstroomoverzicht) | Pijlers die op het ontbrekende overzicht steunen tonen "onvoldoende data"; overige pijlers oordelen normaal |
| FMP-dagquotum bereikt tijdens raadpleging | Systeem meldt quota-uitputting, levert wat lokaal beschikbaar is en geeft aan welke gegevens ontbreken |
| FMP onbereikbaar of time-out | Eerder opgehaalde tickers werken volledig vanuit de buffer; een nieuwe ticker meldt de storing zonder halfgevulde weergave |
| Negatieve of ontbrekende TTM vrije kasstroom als DCF-startwaarde | Calculator blokkeert de Fair Value-weergave en meldt dat een DCF op deze basis niet zinvol is |
| Backfill-run wordt onderbroken | Al opgehaalde periodes blijven bewaard; herstart gaat verder zonder reeds opgeslagen periodes opnieuw op te vragen |
| Herziene historische cijfers (restatement) | Belegger kan per ticker een verversing forceren die de opgeslagen historie vervangt |

---

## 6. Acceptatiecriteria **[verplicht]**

- AC-stock-analyzer1 — Het Pillars-dashboard toont bij invoer van een geldige ticker per pillar de pass/fail-status, de onderliggende waarde(n) en de gehanteerde drempel.
- AC-stock-analyzer2 — Een pillar met minder dan 5 jaar beschikbare historie toont de status "onvoldoende data" en telt niet mee als fail.
- AC-stock-analyzer3 — De DCF-calculator produceert bij elke parameterwijziging direct een herrekende Fair Value per aandeel voor het betreffende scenario, zonder aanvraag richting FMP.
- AC-stock-analyzer4 — Een herhaalde raadpleging van een eerder opgehaalde ticker produceert geen nieuwe FMP-aanvragen voor afgesloten boekjaren en ververst uitsluitend vluchtige gegevens.
- AC-stock-analyzer5 — Bij een bereikt FMP-dagquotum meldt het systeem de quota-uitputting en levert het de lokaal beschikbare gegevens met een markering van wat ontbreekt.
- AC-stock-analyzer6 — Een backfill-run registreert per ticker welke periodes zijn opgehaald en weigert bij herstart reeds opgeslagen periodes opnieuw op te vragen.
- AC-stock-analyzer7 — Bij negatieve of ontbrekende TTM vrije kasstroom blokkeert de calculator de Fair Value-weergave en meldt hij de reden.
- AC-stock-analyzer8 — Het dashboard toont bij een geldige ticker historiegrafieken voor omzet, nettowinst en vrije kasstroom over de beschikbare jaren.
- AC-stock-analyzer9 — De DCF-calculator vermeldt per scenario de marge tussen de Fair Value per aandeel en de actuele koers.
- AC-stock-analyzer10 — Een geforceerde verversing per ticker vervangt de opgeslagen historie door opnieuw opgehaalde gegevens en registreert het moment van verversing.
- AC-stock-analyzer11 — Een aangepaste pillar-drempel is zichtbaar in het dashboard en geldt bij de eerstvolgende beoordeling.
- AC-stock-analyzer12 — Het dashboard toont de current ratio als extra indicator, zichtbaar gescheiden van de acht pijlers en zonder invloed op de pijlerscore.

---

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** geen bestaande PromptManager-flows; dit is een zelfstandig nieuw product. aimm wordt alleen als leesbron geraakt (veldmappings en testdata als referentie), zonder wijziging.
- **Blokkeert:** verificatie dat de FMP gratis tier minimaal 5 jaar jaarcijfers levert en dat de bestaande API-key herbruikbaar is — beide vóór bouwstart (zie Open punten).
- **Blokkeert-door:** n.v.t. — geen ander werk wacht op deze feature.
- **Effect op bestaande data:** n.v.t. — het product krijgt eigen, nieuwe opslag; PromptManager- en aimm-data blijven onaangeroerd.
- **Effect op bestaande gebruikers:** geen — nieuw product voor één gebruiker; niemand hoeft te migreren.
- **Documentlocatie:** deze spec verhuist als founding document mee naar de productrepo zodra die bestaat; tot dat moment is deze kopie in de PromptManager-repo de enige bron.

---

## 8. Validatie en randvoorwaarden **[optioneel]**

- **Precondities:** een geldige FMP API-key is aanwezig; de applicatie is lokaal bereikbaar en meldt bij start de bereikbaarheid van de dataprovider.
- **Invariants:** afgesloten boekjaren wijzigen na opslag niet meer, behoudens expliciet geforceerde verversing; het systeem overschrijdt nooit het dagquotum van de provider.
- **Postcondities:** elke succesvol opgehaalde afgesloten periode is blijvend lokaal beschikbaar, ook wanneer de provider daarna onbereikbaar is of zijn voorwaarden wijzigt.

---

## 10. Open punten **[optioneel]**

- **OP-stock-analyzer-A** — Levert de FMP gratis tier minimaal 5 jaar jaarcijfers? Verificatie met de bestaande aimm-key; belegger beslist daarna over de eenmalige Starter-backfill (~$22, eenmalig) voor 10+ jaar historie. Beslissen vóór bouwstart.
- **OP-stock-analyzer-B** — Levert FMP het rendement op geïnvesteerd vermogen (ROIC) als kant-en-klaar kengetal, of vergt het afleiding uit de jaarrekeningen? Bepaalt de omvang van de rekenlaag; beslissen bij het technisch plan.
- **OP-stock-analyzer-C** — Staan de FMP-voorwaarden blijvende lokale opslag voor persoonlijk gebruik toe, ook na afloop van een betaald plan? Controleren vóór de backfill-run; belegger beslist.
- **OP-stock-analyzer-D** — De pijlertabel in §3 volgt de canonieke Everything Money-set uit publieke bronnen; de EM-site zelf is niet machinaal leesbaar. Verifieer definities en drempels tegen de EM-software bij eerste implementatie; drempels zijn configureerbaar, dus afwijkingen zijn bij te sturen. (Beslist 2026-07-30: current ratio komt terug als extra indicator naast de acht pijlers, niet als pijler — zie §3.)
