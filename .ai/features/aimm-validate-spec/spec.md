# Functionele specificatie: AIMM mechanische specvalidatie

**Feature-map:** `.ai/features/aimm-validate-spec/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmvalspec
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-specauteur krijgt een deterministische gate die structurele contractfouten in een functionele spec blokkeert voordat inhoudelijke review of implementatie start.

Een sjabloon alleen garandeert niet dat verplichte secties substantieel zijn ingevuld, acceptatiecriteria toetsbaar blijven of implementatiedetails uit het functionele contract worden gehouden. Handmatige controle maakt dezelfde fout bovendien afhankelijk van de reviewer.

De validator consumeert de contractrevisie waarvan `new-spec` eigenaar is, beoordeelt uitsluitend mechanische regels en levert reproduceerbare bevindingen. Semantische kwaliteit blijft de verantwoordelijkheid van de aparte specreview.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-specauteur | Draait de validator en herstelt contractfouten | Auteur |
| Mechanische validator | Past vaste regels zonder inhoudelijke interpretatie toe | Alleen-lezen op specs |
| Review- of deliverygate | Gebruikt het resultaat als voorwaarde voor vervolg | Lezer van de validatiestatus |

## 3. Scope **[verplicht]**

### In scope

- Validatie van AIMM-feature-specs en het AIMM-specsjabloon tegen één versieerbaar regelcontract.
- Controle dat document, sjabloon, generator en validator een onderling ondersteunde AIMM-PRD-contractrevisie gebruiken.
- Controle op verplichte secties, scopebalans, minimale happy path, edge cases, toetsbare acceptatiecriteria en impact.
- Controle op unieke en geldige feature-identiteit, onafgemaakte placeholders en implementatie-bleed.
- Statusregels: `draft` mag zichtbare inhoudsgaten bevatten, `in-review` en `accepted` niet; alleen `accepted` kan productimplementatie autoriseren.
- Reproduceerbare bevindingen met regelidentiteit, locatie en reden.
- Een blokkerende status voor contractschendingen.

### Niet in scope

- Beoordelen of productkeuzes verstandig of volledig zijn — dat vereist semantische review.
- De spec automatisch herschrijven — de auteur blijft verantwoordelijk voor betekenis.
- Technische plannen of bugrapporten aan hetzelfde contract onderwerpen — die hebben eigen documentdoelen.
- Een ongeldige spec toch als geslaagd behandelen — uitzonderingen vereisen een zichtbaar nieuw contractbesluit.

## 4. Happy path **[verplicht]**

1. De auteur wijst één AIMM-spec of het canonieke sjabloon aan voor validatie.
2. De validator leest documentstatus en contractrevisie en controleert eerst of die revisie wordt ondersteund.
3. De validator past daarna de vastgelegde mechanische regels in vaste volgorde toe.
4. De validator produceert per overtreding de regelidentiteit, documentlocatie en concrete reden.
5. De auteur herstelt de gemelde contractfouten en voert dezelfde validatie opnieuw uit.
6. Een foutloos document passeert de mechanische gate en kan volgens zijn status naar het toegestane vervolg.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Het opgegeven document ontbreekt of ligt buiten de AIMM-feature-root | De validator weigert de invoer en meldt de toegestane scope. |
| Twee specs delen dezelfde feature-identiteit | De validator blokkeert beide als ambigu contract. |
| Een draft bevat zichtbare inhoudsmarkeringen | De validator meldt welke markeringen vóór een reviewstatus moeten verdwijnen. |
| Een voorbeeld bevat technische syntax | De validator onderscheidt aantoonbare voorbeelden van prescriptieve implementatie en rapporteert volgens het vaste regelcontract. |
| De validator zelf kan een regelconfiguratie niet lezen | De gate faalt gesloten en meldt dat geen betrouwbaar resultaat beschikbaar is. |
| Document en validator gebruiken verschillende contractrevisies | De gate faalt gesloten en vermeldt beide revisies plus de vereiste migratie- of toolactie. |
| Een spec staat op `accepted` maar bevat een onbeantwoord open punt | De validator blokkeert de status en meldt het open punt. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmvalspec1 — De validator produceert voor iedere overtreding een stabiele regelidentiteit, locatie en reden.
- AC-aimmvalspec2 — Een spec met ontbrekende verplichte structuur, ongeldige identiteit of verboden onafgemaakte inhoud faalt de gate.
- AC-aimmvalspec3 — Een geldige spec passeert herhaalbaar met dezelfde uitkomst wanneer document en regelcontract ongewijzigd blijven.
- AC-aimmvalspec4 — De validator weigert een intern verwerkingsprobleem als geslaagde validatie te registreren.
- AC-aimmvalspec5 — De validator verandert de beoordeelde spec niet en vermeldt semantische review als afzonderlijke vervolgstap.
- AC-aimmvalspec6 — De validator toont document- en validatorrevisie en blokkeert iedere niet-ondersteunde combinatie.
- AC-aimmvalspec7 — De validator blokkeert `in-review` en `accepted` bij zichtbare inhoudsgaten en blokkeert `accepted` bij onbeantwoorde open punten.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** AIMM-specgeneratie, specreview en finalisatie van wijzigingen met een functioneel contract.
- **Blokkeert:** semantische review wacht op een mechanisch geldige `in-review`-spec; productimplementatie wacht op een mechanisch geldige `accepted`-spec.
- **Blokkeert-door:** `review-spec` en `finalize-changes` gebruiken de gate-uitkomst.
- **Effect op bestaande data:** geen; de validator leest documenten en rapporteert resultaten.
- **Effect op bestaande gebruikers:** auteurs krijgen snelle, consistente feedback vóór menselijke review.
