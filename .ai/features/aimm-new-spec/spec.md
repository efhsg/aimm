# Functionele specificatie: AIMM specgeneratie

**Feature-map:** `.ai/features/aimm-new-spec/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmnewspec
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-specauteur kan uit een feature-naam of goedgekeurde bron een consistente, niet-overschrijvende functionele spec genereren die klaar is voor mechanische validatie.

AIMM heeft een duurzame feature-root, maar nog geen uniform contract voor nieuwe specs. Handmatig aangemaakte documenten kunnen verplichte scope-, edge-case-, provenance- of impactinformatie missen en daardoor onvoldoende houvast geven aan implementatie en review.

Specgeneratie is eigenaar van het canonieke AIMM-PRD-contract, het bijbehorende sjabloon en de contractrevisionering. Zij beschermt vorm en traceerbaarheid. Bij broninvoer wordt tekst deterministisch gemapt en ontbrekende inhoud zichtbaar gemarkeerd; de generator verzint geen productbesluiten.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-specauteur | Start de generatie en vult inhoudelijke gaten aan | Auteur van het featurecontract |
| Specgenerator | Maakt een nieuw document volgens het AIMM-sjabloon | Schrijft uitsluitend naar de nieuwe feature-map |
| Reviewer | Beoordeelt de ingevulde spec na validatie | Lezer en adviseur |

## 3. Scope **[verplicht]**

### In scope

- Generatie van één nieuwe functionele spec onder de duurzame AIMM-feature-root.
- Lege generatie vanuit een geldige feature-naam en brongestuurde generatie vanuit één Markdown-document.
- Unieke feature-identiteit, aanmaakdatum, verplichte secties en zichtbare inhoudsgaten.
- Eigenaarschap van het canonieke AIMM-PRD-contract, de statussen `draft`, `in-review` en `accepted` en één zichtbare contractrevisionering.
- Bescherming tegen stilzwijgend overschrijven van een bestaande spec.
- Handoff naar mechanische validatie en daarna semantische review.

### Niet in scope

- Productkeuzes invullen die niet in de bron staan — de auteur blijft inhoudelijk verantwoordelijk.
- Een bestaande spec automatisch overschrijven — bestaand werk moet expliciet worden bewerkt.
- Meerdere onafhankelijke features tot één spec samenvoegen — iedere feature houdt een eigen contract.
- Technische implementatiekeuzes voorschrijven — die horen in een plan wanneer nodig.

## 4. Happy path **[verplicht]**

1. De auteur levert een geldige feature-naam en optioneel één goedgekeurd brondocument.
2. De generator controleert dat de doelmap geen bestaande spec bevat en dat de feature-identiteit uniek is.
3. De generator maakt een spec volgens de actuele AIMM-PRD-contractrevisie en vult alleen inhoud in die volgens de vaste kopmapping herkend is.
4. De generator markeert iedere ontbrekende verplichte inhoud als auteurswerk en produceert naast de spec een gap-report met gemapte, ongedekte en niet-herkende bronsecties.
5. De auteur vult de gaten aan en start de mechanische specvalidatie.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De feature-naam is ongeldig of botst met een bestaande identiteit | De generator weigert generatie en meldt de vereiste correctie. |
| De doelspec bestaat al | De generator blokkeert zonder het bestaande bestand te wijzigen. |
| De bron beschrijft meerdere onafhankelijke features | De generator vraagt welke feature in scope is en schrijft niets vóór de keuze. |
| Een bronsectie past niet eenduidig op het sjabloon | De generator laat de doelsectie zichtbaar ongedekt en rapporteert de bronsectie als niet gemapt. |
| De bron bevat financiële claims zonder provenance | De generator kopieert geen impliciete waarheid en markeert bron, periode of eenheid als inhoudsgat. |
| De generator en validator ondersteunen verschillende contractrevisies | De generator blokkeert oplevering en meldt beide revisies zonder een spec als valide te presenteren. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmnewspec1 — Geldige invoer produceert precies één nieuwe AIMM-spec met unieke feature-identiteit, datum en alle verplichte secties.
- AC-aimmnewspec2 — Een bestaande doelspec blokkeert generatie en blijft byte-voor-byte ongewijzigd.
- AC-aimmnewspec3 — Brongestuurde generatie bevat uitsluitend aantoonbaar gemapte broninhoud en vermeldt alle ongedekte verplichte secties.
- AC-aimmnewspec4 — De generator weigert meerdere onafhankelijke features zonder expliciete scopekeuze samen te voegen.
- AC-aimmnewspec5 — De uitvoer vermeldt de vervolgstappen mechanische validatie en semantische review.
- AC-aimmnewspec6 — Iedere nieuwe spec vermeldt de gebruikte AIMM-PRD-contractrevisie en de generator blokkeert wanneer de validator die revisie niet ondersteunt.
- AC-aimmnewspec7 — De gap-reportage toont per verplichte sectie de gemapte bronkop of de reden dat auteurswerk nodig blijft en noemt iedere niet-herkende bronsectie.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** duurzame AIMM-featureartefacten, het canonieke AIMM-PRD-contract en de overdracht van preflight naar specificatie.
- **Blokkeert:** betrouwbare generatie wacht op een vastgesteld AIMM-speccontract.
- **Blokkeert-door:** mechanische validatie en semantische review gebruiken het gegenereerde document.
- **Effect op bestaande data:** bestaande specs blijven ongewijzigd; alleen een nieuwe feature-map wordt toegevoegd.
- **Effect op bestaande gebruikers:** auteurs krijgen een voorspelbare start en zien ontbrekende beslissingen eerder.

## 8. AIMM-PRD-contract **[optioneel]**

### Statussen

| Status | Betekenis | Toegestaan vervolg |
|--------|-----------|--------------------|
| `draft` | De auteur werkt nog aan inhoud; zichtbare inhoudsgaten mogen bestaan. | Documentbewerking en mechanische feedback; geen productimplementatie op basis van deze spec. |
| `in-review` | De spec bevat geen inhoudsgaten en passeert de mechanische gate. | Semantische en menselijke review; nog geen productimplementatie. |
| `accepted` | De mechanische gate passeert, open punten zijn besloten en de eigenaar heeft het functionele contract geaccepteerd. | Planning en productimplementatie mogen starten. |

Een nieuwe contractrevisie is pas bruikbaar wanneer sjabloon, generator en validator dezelfde revisie ondersteunen. Een bestaande accepted spec behoudt haar geregistreerde revisie; een migratie naar een nieuw contract is een afzonderlijke, zichtbare auteursactie.

### Deterministische bronmapping

De generator herkent H2- en H3-koppen hoofdletterongevoelig volgens deze betekenissen:

| Bronbetekenis | Doelsectie |
|---------------|------------|
| doel, context, goal, purpose | §1 Doel en context |
| actoren, rollen, actors, roles, stakeholders | §2 Actoren |
| scope, in scope, niet in scope, out of scope | §3 Scope |
| flow, happy path, workflow, stappen, steps | §4 Happy path |
| edge cases, randgevallen, exceptions | §5 Edge cases |
| acceptatiecriteria, acceptance criteria, AC | §6 Acceptatiecriteria |
| impact, afhankelijkheden, dependencies | §7 Impact op bestaande flows en data |

De eerste match bepaalt de primaire bronsectie; latere matches voor hetzelfde doel worden als afzonderlijk vervolgblok toegevoegd. De body wordt letterlijk overgenomen. Niet-herkende koppen worden niet geïnterpreteerd en verschijnen in het gap-report. Wanneer meerdere kandidaatfeatures herkenbaar zijn, schrijft de generator niets voordat de auteur één scope kiest.
