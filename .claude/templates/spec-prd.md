# Functionele specificatie: <feature-name>

**Feature-map:** `.ai/features/<feature-name>/`
**Status:** draft
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** <slug>
**Aangemaakt:** <yyyy-mm-dd>

<!--
AIMM-PRD-1 beschrijft WAT en WAAROM, niet HOE.

Secties 1 t/m 7 zijn verplicht en blijven in deze volgorde. Secties 8 t/m 10
zijn optioneel; verwijder een optionele sectie wanneer die niet van toepassing
is. Een draft mag zichtbare <placeholders> bevatten. Een in-review- of
accepted-spec niet. Alleen accepted autoriseert productimplementatie.

Financiële claims vermelden waar relevant bron, verslagperiode, eenheid,
transformatie en validatiestatus. Ontbrekende of conflicterende gegevens blijven
zichtbaar als auteursgat; verzin nooit een waarde.

Mechanisch contract voor /validate-spec:
R1  Verplichte H2-secties 1 t/m 7 zijn aanwezig, staan in volgorde en eindigen
    letterlijk op **[verplicht]**.
R2  Sectie 1 bevat een kernbericht en minstens één contextalinea.
R3  Sectie 2 bevat minstens één actorrij.
R4  Sectie 3 bevat In scope en Niet in scope; iedere uitsluiting heeft een
    reden na " — ".
R5  Sectie 4 bevat minstens drie genummerde stappen.
R6  Sectie 5 bevat minstens drie edge-caserijen.
R7  Sectie 6 bevat minstens drie AC-<slug>N-criteria met een toetsbaar werkwoord.
R8  Sectie 7 bevat Raakt, Blokkeert, Blokkeert-door en effecten op bestaande
    data en gebruikers.
R9  De spec bevat buiten fenced voorbeelden geen prescriptieve implementatie.
R10 Status en contractrevisie zijn geldig; niet-drafts bevatten geen zichtbare
    auteursgaten en accepted bevat geen onbeantwoorde open punten.
R11 De feature-slug is uniek onder .ai/features/*/spec.md.
R12 De slug voldoet aan ^[a-z][a-z0-9-]{2,23}$.
-->

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** <wat levert deze feature op voor wie, en waarom nu?>

<welk probleem lost dit op, welke bestaande flow raakt dit en wat is de aanleiding?>

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| <actor> | <rol in deze feature> | <eigenaar, gebruiker, lezer of beheerder> |

## 3. Scope **[verplicht]**

### In scope

- <gedrag dat deze feature expliciet dekt>

### Niet in scope

- <verwant gedrag dat bewust niet wordt gedekt> — <waarom niet>

## 4. Happy path **[verplicht]**

1. <actor voert de eerste functionele stap uit>.
2. <het systeem levert het waarneembare resultaat>.
3. <actor of systeem rondt de flow af>.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| <ontbrekende, ongeldige of conflicterende invoer> | <waarneembaar gedrag> |
| <afhankelijkheid is niet beschikbaar> | <waarneembaar gedrag> |
| <actie wordt herhaald of afgebroken> | <waarneembaar gedrag> |

## 6. Acceptatiecriteria **[verplicht]**

- AC-<slug>1 — <onderwerp> toont <waarneembaar resultaat> bij <trigger>.
- AC-<slug>2 — <actie> produceert <waarneembaar resultaat> binnen <grens>.
- AC-<slug>3 — <systeem> blokkeert <ongewenste toestand> en meldt <reden>.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** <bestaande features, entiteiten of flows>
- **Blokkeert:** <wat eerst gereed moet zijn>
- **Blokkeert-door:** <wat op deze feature wacht>
- **Effect op bestaande data:** <effect op records, output en koppelingen; inclusief provenance, periode, eenheid of transformatie waar relevant>
- **Effect op bestaande gebruikers:** <zichtbare verandering of benodigde overgang>

## 8. Validatie en randvoorwaarden **[optioneel]**

- **Precondities:** <wat vooraf waar moet zijn>
- **Invariants:** <wat tijdens de hele flow waar blijft>
- **Postcondities:** <wat na afloop gegarandeerd waar is>

## 9. Toestandsmachine **[optioneel]**

Entiteit: <naam>

| Toestand | Betreedbaar vanuit | Exit naar | Actor die transitie triggert |
|----------|--------------------|-----------|--------------------------------|
| <start> | — | <volgende toestand> | <actor> |

Verboden overgangen: <welke overgangen niet zijn toegestaan>.

## 10. Open punten **[optioneel]**

- **OP-<slug>-A** — <open vraag, beslisser en beslismoment>
