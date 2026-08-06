# Functionele specificatie: AIMM semantische specreview

**Feature-map:** `.ai/features/aimm-review-spec/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmrevspec
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-specauteur krijgt na mechanische validatie een sectiegewijze inhoudsaudit die onduidelijke scope, onbewezen financiële aannames en ontoetsbare uitkomsten zichtbaar maakt zonder de spec te wijzigen.

Een mechanisch geldige spec kan inhoudelijk nog steeds vaag, intern tegenstrijdig of onvoldoende herleidbaar zijn. In AIMM is dat extra riskant wanneer bron, periode, eenheid, transformatie of gedrag bij ontbrekende data niet expliciet is.

De review is adviserend en geeft geen automatisch goedkeuringsverdict. De auteur of menselijke reviewer beslist welke bevindingen worden verwerkt.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-specauteur | Vraagt de review aan en verwerkt bevindingen | Auteur en beslisser |
| Semantische reviewer | Beoordeelt iedere aanwezige sectie tegen het AIMM-contract | Alleen-lezen en adviserend |
| AIMM-domeinreviewer | Bevestigt financiële of operationele keuzes wanneer nodig | Menselijke reviewer |

## 3. Scope **[verplicht]**

### In scope

- Sectiegewijze audit van doel, actoren, scope, happy path, edge cases, acceptatiecriteria en impact.
- Controle op substantie, toetsbaarheid, interne consistentie en functionele versus technische grens.
- AIMM-specifieke aandacht voor provenance, bronconflicten, perioden, eenheden, ontbrekende waarden en determinisme.
- Maximaal drie geprioriteerde bevindingen per aanwezige sectie, ieder met urgentie en concrete verbeterrichting.

### Niet in scope

- Een spec met open mechanische contractfouten inhoudelijk auditen — vormfouten gaan eerst terug naar validatie.
- De spec automatisch aanpassen — auteurschap en productbeslissingen blijven menselijk gestuurd.
- Een binair goedkeuringsverdict geven — de review levert advies en bewijs.
- Implementatiecode beoordelen — daarvoor bestaat `review-changes`.

## 4. Happy path **[verplicht]**

1. De auteur levert een mechanisch geldige AIMM-spec aan.
2. De reviewer leest het volledige document en controleert de samenhang tussen alle secties.
3. De reviewer toetst relevante financiële claims en flows op expliciete bron-, periode-, eenheids- en foutgrenzen.
4. De review produceert per sectie maximaal drie belangrijkste bevindingen met urgentie en verbeterrichting.
5. De auteur verwerkt of beantwoordt de bevindingen; daarna zet de bevoegde eigenaar de status expliciet op `accepted`, waarna implementatieplanning en productimplementatie zijn toegestaan.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De mechanische validator meldt nog fouten | De semantische review blokkeert en verwijst terug naar de concrete validatiebevindingen. |
| Een optionele sectie ontbreekt terecht | De review fabriceert geen bevinding alleen vanwege de afwezigheid. |
| Een financiële uitkomst mist bron, periode of eenheid | De review markeert dit als hoge urgentie wanneer betrouwbaarheid of vergelijkbaarheid wordt geraakt. |
| Happy path en acceptatiecriteria spreken elkaar tegen | De review toont beide passages en beschrijft welk productbesluit ontbreekt. |
| De spec bevat technische oplossingsdetails | De review meldt de grensoverschrijding en adviseert verplaatsing naar een plan zonder zelf een ontwerp te kiezen. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmrevspec1 — De review produceert voor iedere aanwezige specsectie bevindingen of vermeldt expliciet dat geen materiële bevinding bestaat.
- AC-aimmrevspec2 — Iedere bevinding bevat urgentie, bewijs uit de spec en een concrete verbeterrichting.
- AC-aimmrevspec3 — De review meldt ontbrekende provenance, periode, eenheid of gedrag bij ontbrekende financiële data wanneer die relevant zijn.
- AC-aimmrevspec4 — De review blokkeert wanneer de mechanische specgate niet passeert.
- AC-aimmrevspec5 — De review verandert de spec niet en levert geen automatisch goedkeuringsverdict.
- AC-aimmrevspec6 — De review toont per aanwezige sectie maximaal drie bevindingen en ordent die op urgentie.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** AIMM-specificatiekwaliteit en de overgang van gevalideerde spec naar planning of implementatie.
- **Blokkeert:** inhoudelijke review wacht op een geslaagde mechanische validatie.
- **Blokkeert-door:** technische planning en implementatie vereisen beantwoorde reviewbevindingen én de expliciete status `accepted` als kwaliteitsbewijs.
- **Effect op bestaande data:** geen; de review is read-only.
- **Effect op bestaande gebruikers:** auteurs en domeinreviewers krijgen kortere, beter geprioriteerde feedback op risicovolle onduidelijkheden.
