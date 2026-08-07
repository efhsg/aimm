# Functionele specificatie: MoneyMonkey Portfolio Review

**Feature-map:** `.ai/features/moneymonkey-portfolio-review/`
**Status:** draft
**Feature-slug:** mm-review
**Aangemaakt:** 2026-06-14 00:00 UTC

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** MoneyMonkey levert de belegger een periodiek portfolio-reviewrapport dat vaste risicoregels combineert met AI-duiding, zodat de gebruiker onderbouwd kan beslissen zonder dat het systeem transacties uitvoert.

MoneyMonkey heeft al een inhoudelijke richting rond fundamentele aandelenanalyse, buy/hold/sell-duiding en portfolio-rebalancing. De huidige domeinbasis bevat aandelen, sectoren, industries, databronnen, metrics, dividendrendementen en koershistorie. Wat nog ontbreekt is een bovenliggende gebruikersflow die deze data vertaalt naar periodieke portfolio-inzichten.

Deze feature introduceert een mens-in-de-loop reviewproces. Het systeem beoordeelt een portfolio-snapshot tegen een beleggingsprofiel, markeert afwijkingen en produceert een rapport met signalen, AI-observaties en voorgestelde vervolgstappen. De gebruiker blijft eigenaar van elk beleggingsbesluit.

---

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| Belegger | Beheert het beleggingsprofiel, voert portfolio-snapshots in en neemt besluiten op basis van reviews. | Eigenaar |
| MoneyMonkey | Controleert snapshots tegen vaste regels en bewaart reviewrapporten en besluitlogs. | Systeem binnen eigenaarschap |
| AI-analist | Duidt signalen, vat risico's samen en formuleert mogelijke vervolgstappen. | Geen zelfstandige beslisrechten |
| Databron | Levert koers-, dividend-, metric- of marktcontext die in een review gebruikt kan worden. | Alleen bronvermelding |

---

## 3. Scope **[verplicht]**

### In scope

- Vastleggen van een beleggingsprofiel met doel, horizon, risicotolerantie, doelallocatie en concentratielimieten.
- Vastleggen of importeren van een portfolio-snapshot met posities, aantallen, waarde, cash en peildatum.
- Controleren van een snapshot op allocatie-afwijking, concentratie, ontbrekende data, prijsbewegingen en relevante metricwijzigingen.
- Produceren van een portfolio-reviewrapport met status, signalen, AI-duiding, aanbevolen vervolgstappen en bronvermelding.
- Registreren van het menselijke besluit per signaal of review, inclusief rationale.
- Onderscheiden van regelgebaseerde signalen en AI-observaties in alle review-output.

### Niet in scope

- Automatisch uitvoeren van koop- of verkooporders — de eerste versie is besluitondersteuning en mag geen transacties namens de gebruiker plaatsen.
- Intraday trading, optiestrategieën of hefboomproducten — deze vragen andere risicomodellen en passen niet bij een eerste portfolio-reviewflow.
- Brokerkoppeling als verplichte input — handmatige snapshots moeten de feature bruikbaar maken zonder externe afhankelijkheid.
- Belastingadvies of juridisch advies — het reviewrapport behandelt portfoliofit en risico, niet individuele fiscale of juridische gevolgen.
- Garantie op rendement of juistheid van AI-advies — de feature ondersteunt besluitvorming maar vervangt geen professionele beleggingsbeoordeling.

---

## 4. Happy path **[verplicht]**

1. De belegger legt een beleggingsprofiel vast met doelallocatie, risicotolerantie en concentratiegrenzen.
2. De belegger voert een portfolio-snapshot in of importeert die met posities, actuele waarden, cash en peildatum.
3. MoneyMonkey controleert de snapshot op vaste regels en vergelijkt waar mogelijk met eerdere snapshots.
4. MoneyMonkey vraagt AI-duiding voor de gevonden signalen, beschikbare metrics en relevante context.
5. MoneyMonkey produceert een reviewrapport met status, signalen, bronvermelding, AI-observaties en mogelijke vervolgstappen.
6. De belegger registreert per relevant signaal een besluit, zoals niets doen, herbalanceren overwegen, extra onderzoek doen of negeren.
7. MoneyMonkey bewaart het rapport en de besluiten als historisch referentiepunt voor latere reviews.

---

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Snapshot mist actuele koers of waarde voor een positie | De review meldt de ontbrekende data, markeert de positie als niet volledig beoordeeld en voorkomt conclusies die van die waarde afhangen. |
| Beleggingsprofiel ontbreekt of is onvolledig | MoneyMonkey blokkeert de review en meldt welke profielgegevens nodig zijn voordat beoordeling mogelijk is. |
| Een positie overschrijdt meerdere limieten tegelijk | Het rapport groepeert de signalen per positie en toont per signaal de afzonderlijke reden. |
| Er bestaat geen vorige snapshot | MoneyMonkey produceert een eerste nulmeting en vermeldt dat trend- en delta-analyse nog niet beschikbaar is. |
| AI-duiding faalt of levert lege output | De regelgebaseerde signalen blijven zichtbaar; het rapport meldt dat AI-duiding niet beschikbaar is. |
| Databronnen spreken elkaar tegen | De review toont bronconflict als apart signaal en gebruikt geen samengevoegde conclusie zonder bronkeuze. |
| Gebruiker wijzigt het beleggingsprofiel na een eerdere review | Historische rapporten blijven gebaseerd op het profiel dat destijds gold; nieuwe reviews gebruiken het actuele profiel. |
| Twee reviews worden voor dezelfde snapshot gestart | MoneyMonkey blokkeert dubbele actieve reviews of meldt duidelijk welke review leidend is. |

---

## 6. Acceptatiecriteria **[verplicht]**

- AC-mm-review1 — Beleggingsprofiel toont doel, horizon, risicotolerantie, doelallocatie en concentratielimieten bij het starten van een portfolio-review.
- AC-mm-review2 — Portfolio-snapshot produceert een peildatum, posities, aantallen, waarden, cashpositie en totale portfoliowaarde binnen het reviewrapport.
- AC-mm-review3 — Review blokkeert beoordeling en meldt ontbrekende profielgegevens wanneer het beleggingsprofiel niet reviewbaar is.
- AC-mm-review4 — Reviewrapport bevat regelgebaseerde signalen apart van AI-observaties bij elke afgeronde review.
- AC-mm-review5 — Reviewrapport vermeldt per signaal of het ontstaat door allocatie-afwijking, concentratie, ontbrekende data, prijsbeweging, metricwijziging of AI-duiding.
- AC-mm-review6 — Eerste review zonder eerdere snapshot produceert een nulmeting en vermeldt dat trendanalyse niet beschikbaar is.
- AC-mm-review7 — Review met ontbrekende brondata produceert een gedeeltelijk rapport en meldt welke conclusies niet toetsbaar zijn.
- AC-mm-review8 — Besluitregistratie registreert per gekozen vervolgstap de keuze, rationale en datum bij de review.
- AC-mm-review9 — Historisch reviewrapport toont het beleggingsprofiel en de snapshot waarop het rapport gebaseerd was.
- AC-mm-review10 — MoneyMonkey weigert automatische transactie-uitvoering vanuit een review en meldt dat de gebruiker zelf verantwoordelijk blijft voor uitvoering.

---

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** aandelenanalyse, portfolio management, databronnen, koershistorie, dividendrendementen, financial metrics en gebruikersspecifieke configuratie.
- **Blokkeert:** een bruikbare portfolio-review vereist minimaal een beleggingsprofiel en een portfolio-snapshot met posities en peildatum.
- **Blokkeert-door:** latere scheduling, brokerimport, notificaties en geautomatiseerde herbalanceeradviezen wachten op dit reviewfundament.
- **Effect op bestaande data:** bestaande aandelen-, metric-, dividend- en koersdata blijven brondata; historische reviews krijgen eigen momentopname en mogen niet meeveranderen als brondata later wordt aangepast.
- **Effect op bestaande gebruikers:** bestaande configuratie- en aandelenflows blijven bruikbaar; gebruikers krijgen pas nieuw gedrag wanneer zij een beleggingsprofiel en snapshot voor review gebruiken.

---

## 8. Validatie en randvoorwaarden **[optioneel]**

- **Precondities:** de gebruiker heeft een beleggingsprofiel met minimaal doel, horizon, risicotolerantie en doelallocatie.
- **Precondities:** de snapshot bevat minimaal een peildatum, cashpositie en één positie of expliciet lege portfolio.
- **Invariants:** AI-output is altijd duiding of suggestie en nooit een uitgevoerde actie.
- **Invariants:** een reviewrapport verwijst naar de snapshot en het beleggingsprofiel waarop het gebaseerd is.
- **Invariants:** regelgebaseerde signalen blijven zichtbaar wanneer AI-duiding ontbreekt.
- **Postcondities:** een afgeronde review levert een rapport op dat later opnieuw gelezen kan worden zonder herberekening.
- **Postcondities:** elk geregistreerd besluit blijft gekoppeld aan de reviewcontext waarin het genomen is.

---

## 9. Toestandsmachine **[optioneel]**

Entiteit: Portfolio-review

| Toestand | Betreedbaar vanuit | Exit naar | Actor die transitie triggert |
|----------|--------------------|-----------| -----------------------------|
| concept | — (initial) | klaar-voor-review, geannuleerd | Belegger |
| klaar-voor-review | concept | in-review, geannuleerd | Belegger |
| in-review | klaar-voor-review | afgerond, mislukt | MoneyMonkey |
| afgerond | in-review | besluit-vastgelegd | Belegger |
| besluit-vastgelegd | afgerond | — | Belegger |
| mislukt | in-review | concept | Belegger |
| geannuleerd | concept, klaar-voor-review | — | Belegger |

Verboden overgangen: een afgeronde review mag niet terug naar in-review; een besluit mag niet worden geregistreerd op een mislukte of geannuleerde review; een review mag niet naar in-review zonder reviewbare snapshot en profiel.

---

## 10. Open punten **[optioneel]**

- **OP-mm-review-A** — Moet de eerste versie alleen handmatige snapshots ondersteunen, of ook CSV-import?
- **OP-mm-review-B** — Welke standaard assetclasses en concentratielimieten wil MoneyMonkey aanbieden bij een nieuw beleggingsprofiel?
- **OP-mm-review-C** — Is buy/hold/sell-taal toegestaan in de eerste versie, of moet de output neutraler blijven met acties als bewaken, onderzoeken en herbalanceren?
- **OP-mm-review-D** — Moet een review één portfolio behandelen, of meerdere portfolio's per gebruiker ondersteunen vanaf de eerste versie?
