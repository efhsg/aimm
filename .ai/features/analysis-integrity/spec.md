# Functionele specificatie: AIMM-analyse-integriteit

**Feature-map:** `.ai/features/analysis-integrity/`
**Status:** in-review
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** analysis-integrity
**Aangemaakt:** 2026-08-07

<!--
AIMM-PRD-1 beschrijft WAT en WAAROM, niet HOE.

Secties 1 t/m 7 zijn verplicht en blijven in deze volgorde. Secties 8 t/m 10
zijn optioneel; verwijder een optionele sectie wanneer die niet van toepassing
is. Alleen een accepted-spec autoriseert productimplementatie.

Financiële claims vermelden waar relevant bron, verslagperiode, eenheid,
transformatie en validatiestatus. Ontbrekende of conflicterende gegevens blijven
zichtbaar; een ontbrekende waarde wordt nooit aangevuld om een analyse te laten
slagen.
-->

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** Een verifieerbare analyse legt voor de belegger bij elk nieuw AIMM-rapport de gebruikte gegevens, aannames en rekenroute vast en controleert de uitkomst door herberekening, zodat een BUY/HOLD/SELL-oordeel reproduceerbaar en van onvoldoende bewijs te onderscheiden is.

AIMM bewaart broninformatie bij verzamelde financiële gegevens en produceert deterministische ranglijsten en rapporten. In de huidige rapportuitkomst zijn de exacte invoerselectie, de effectieve methodologieregels, de waarschuwingen en de volledige afleiding naar het oordeel echter niet als één onveranderlijk geheel zichtbaar. Daardoor kan een rekenkundig correct rapport afwijken van de vastgelegde beleggingsmethodologie zonder dat de belegger dat in het rapport kan vaststellen.

Deze feature voegt aan ieder afgerond nieuw analyserapport een onveranderlijk analysemanifest, een onafhankelijke herberekeningscontrole en een bewijsstatus toe; een voortijdig afgebroken run bewaart in plaats daarvan een zichtbaar controlespoor zonder advies. De bewijsstatus beschrijft de betrouwbaarheid van de analyse; het beleggingsadvies blijft een afzonderlijke conclusie. Een gebrek aan voldoende bewijs resulteert daarom niet automatisch in HOLD, maar in uitsluiting of blokkering met een zichtbare reden.

Een gedeelde integriteitsfout, zoals een onvolledig manifest, een ongeldige peerreferentie of een afwijkende rapportherberekening, blokkeert het volledige rapport. Een probleem dat aantoonbaar tot één onderneming beperkt blijft, sluit uitsluitend die onderneming uit; wanneer de resterende peerbasis geldig blijft, kan het rapport met de status gekwalificeerd en met expliciete uitsluiting worden gepubliceerd. Geverifieerd is uitsluitend mogelijk wanneer geen geconfigureerde onderneming is uitgesloten en alle ondernemings- en gedeelde controles zonder waarschuwing zijn geslaagd.

Als eerste methodologiecontract gelden de bestaande AIMM-principes dat een structurele trend minimaal vijf vergelijkbare afgesloten boekjaren vereist en dat een onderneming tegen de mediaan van de overige geldige sectorgenoten wordt afgezet. Verdere uitbreiding van de forensische analysemethode valt buiten deze feature.

Een waarderingsmaatstaf heeft voor een onderneming minimaal drie overige geldige peers nodig. Vier of meer overige peers voldoen zonder dekkingswaarschuwing; precies drie overige peers maken de maatstaf bruikbaar met een kwalificerende waarschuwing; bij minder dan drie vervalt de maatstaf. Verschillende maatstaven mogen verschillende geldige peers hebben wanneer die selectie per maatstaf zichtbaar is. Een onderneming heeft minimaal twee bruikbare waarderingsmaatstaven nodig voor een advies.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| Belegger | Start een analyse en beoordeelt advies, bewijsstatus en onderliggende rekenroute | Lezer van rapporten en auditinformatie; kan een bestaande verificatie-uitkomst niet wijzigen |
| AIMM-beheerder | Beheert industriegroep en gegevensbeleid en dient kandidaat-methodologieversies in | Eigenaar van de operationele configuratie; kan alleen via een nieuwe versie toekomstige analyses beïnvloeden en kan een eigen kandidaat-methodologieversie niet accepteren |
| AIMM-datasteward | Beoordeelt ontbrekende of conflicterende herkomst tegen het versieerbare gegevensbeleid | Kan met een vastgelegde beleidsregel en reden een invoer ongeschikt verklaren en een nieuwe gegevensverzameling of correctieversie initiëren; kan een bestaand datapunt of manifest niet achteraf aanpassen en mag een geldige invoer niet wegens haar effect op het advies uitsluiten |
| AIMM-verzamelproces | Selecteert bronnen volgens het actieve gegevensbeleid en legt waarden, perioden, eenheden, transformaties en herkomst vast | Geautomatiseerde actor; levert alleen versieerbare gegevens aan en kan geen analyseadvies of bewijsstatus bepalen |
| AIMM-analyseproces | Bevriest de analysecontext en produceert via de oorspronkelijke rekenroute het manifest en de oorspronkelijke uitkomst | Geautomatiseerde actor; kan geen definitieve bewijsstatus aan zichzelf toekennen en kan een definitief manifest niet wijzigen |
| AIMM-verificatieproces | Herberekent via de functioneel gescheiden verificatieroute de normalisaties en analyse-uitkomst en bepaalt de verificatiebevindingen | Geautomatiseerde actor met alleen-lezen toegang tot het definitieve manifest; mag precies één definitieve verificatie-uitkomst aan de manifestvingerafdruk binden en oorspronkelijke afleidingen alleen als vergelijkingsuitkomst, nooit als rekeninvoer, lezen |
| Financieel reviewer | Accepteert, wijst af of trekt methodologieversies in en beoordeelt afwijkingen en blokkerende controles | Inhoudelijk beslisser over de methodologiestatus; kan de inhoud van een geaccepteerde versie niet wijzigen en kan een geblokkeerde analyse niet handmatig als geverifieerd markeren |
| Externe gegevensbron | Levert financiële en marktgegevens met bron-, periode- en eenheidsinformatie | Extern systeem zonder toegang tot AIMM-rapporten of verificatiestatussen |

## 3. Scope **[verplicht]**

### In scope

- Een onveranderlijk analysemanifest per nieuw rapport met een bij runstart toegekend manifest-ID, rapportidentiteit, analyse- en gegevenspeildatum, exacte ondernemings- en peerselectie, gebruikte oorspronkelijke en genormaliseerde invoerwaarden, afgewezen kandidaat-invoer met reden, verslagperioden, eenheden en valuta, bronverwijzingen, ophaalmomenten, transformaties, bestaande validatie-uitkomsten, iedere daadwerkelijk berekende oorspronkelijke ongeronde analyse-uitkomst en per niet-uitgevoerde berekening een expliciete reden, manifestrepresentatieversie en een inhoudsafgeleide manifestvingerafdruk die de definitieve inhoud aan het rapport bindt.
- Vastlegging van de volledige effectieve analysecontext: methodologieversie, beleidsversie, gebruikte formules, drempels, gewichten, minimumvereisten, numerieke toleranties, toegestane waarschuwingen en afzonderlijke identiteiten voor de oorspronkelijke rekenroute en de verificatieroute. Iedere route-identiteit bestaat uit AIMM-release, volledige bronrevisie of build-artefactvingerafdruk, dependency-lockvingerafdruk, runtimeversie en routeversie. Een bronrevisie is alleen afdoende wanneer de uitgevoerde productcode aantoonbaar ongewijzigd met die revisie overeenkomt; anders is een vingerafdruk van het werkelijk uitgevoerde build-artefact verplicht.
- Een integriteitscontrole die bij iedere verificatie en rapportweergave vaststelt dat rapportidentiteit, manifest-ID, manifestrepresentatieversie, manifestvingerafdruk, een vóór publicatie vastgelegd append-only integriteitsanker en de definitieve canonieke manifestinhoud nog overeenstemmen; een ontbrekende of afwijkende binding geldt als een gedeelde integriteitsfout. Het integriteitsanker valt buiten de wijzigingsroute van manifest en rapport en bewaart minimaal rapportidentiteit, manifest-ID, manifestvingerafdruk en vastleggingstijdstip.
- Een functioneel gescheiden herberekening vanuit uitsluitend de bevroren oorspronkelijke bronwaarden, herkomst, periode-, eenheids- en valutagegevens, versieerbare transformaties en de geaccepteerde methodologie. De verificatieroute herberekent eerst de genormaliseerde invoerwaarden en hergebruikt geen door de oorspronkelijke route berekende of opgeslagen normalisatie, peerreferentie, waarderingsverschil, score, regelpad, advies, rang of afgeleide cache; iedere herberekende uitkomst wordt met de oorspronkelijke ongeronde uitkomst vergeleken.
- Een bewijsstatus op rapportniveau en per geanalyseerde onderneming: geverifieerd bij volledige overeenstemming zonder uitsluiting, gekwalificeerd bij uitsluitend toegestane niet-blokkerende waarschuwingen of correct verwerkte ondernemingsspecifieke uitsluitingen, en geblokkeerd bij een gedeelde integriteitsfout of onvoldoende bewijs voor het gepubliceerde bereik.
- Aggregatie van bewijsstatussen: een gedeelde blokkerende bevinding blokkeert het rapport; een ondernemingsspecifieke uitsluiting leidt alleen tot een gekwalificeerd rapport wanneer de resterende peerbasis en alle gedeelde controles geldig blijven; geverifieerd vereist dat iedere opgenomen onderneming en iedere gedeelde controle zonder waarschuwing is geslaagd.
- Een afzonderlijke geschiktheidsuitkomst voor uitgesloten ondernemingen, zodat ontbrekende gegevens nooit als HOLD worden geïnterpreteerd en de gevolgen voor de peergroep zichtbaar blijven.
- Methodologiecontrole op minimaal vijf vergelijkbare afgesloten boekjaren voor iedere structurele trend die het advies beïnvloedt.
- Methodologiecontrole waarbij iedere onderneming per waarderingsmaatstaf wordt vergeleken met de mediaan van de overige geldige peers, zonder de eigen waarde in de referentie op te nemen; vier of meer overige peers voldoen volledig, precies drie veroorzaken een kwalificerende dekkingswaarschuwing en minder dan drie maken de maatstaf onbruikbaar. Een peer is uitsluitend geldig wanneer zij tot de bevroren industriegroep behoort, op het door de methodologie vereiste niveau binnen de vastgelegde versie van de sectortaxonomie valt, voor dezelfde maatstafbasis een toegestane waarde bezit en aan de vastgelegde periode-, eenheids-, valuta- en actualiteitsregels voldoet. Negatieve, nul- en uitzonderlijke waarden worden alleen volgens vooraf versieerbare maatstafregels opgenomen of uitgesloten; ad-hocuitsluiting is niet toegestaan.
- Maatstafspecifieke peerdekking, waarbij ontbrekende peerwaarden alleen de betreffende maatstaf raken, iedere afwijkende peerset zichtbaar blijft en een onderneming minimaal twee bruikbare waarderingsmaatstaven nodig heeft voor een advies.
- Vaste bevindingenclassificatie die onderscheid maakt tussen informatief, kwalificerend, ondernemingsblokkerend en rapportblokkerend gedrag op basis van de functionele gevolgen voor advies, peerreferentie en reproduceerbaarheid.
- Een zichtbare uitleg van bronwaarde via periode-, eenheid- en valutatransformatie, formule, drempel, scorebijdrage en regelpad naar advies en rang.
- Een verificatiesamenvatting in de webweergave en PDF-uitvoer met bewijsstatus, dekkingsgraad, uitgesloten ondernemingen, waarschuwingen, blokkerende bevindingen en verwijzing naar het analysemanifest.
- Deterministische herhaling: dezelfde bevroren oorspronkelijke invoer en dezelfde beleids-, methodologie-, rekenroute- en verificatierouteversies leveren dezelfde ongeronde genormaliseerde waarden, analyse-uitkomst, rang en verificatiebevindingen.
- Behoud van bestaande rapporten als historische uitkomst, met een zichtbare aanduiding dat rapporten zonder manifest niet volgens dit contract zijn geverifieerd en een expliciete actie om met actuele gegevens en regels een nieuwe analyse te starten zonder het historische rapport te wijzigen.

### Niet in scope

- DCF-, bull/base/bear- of volledige drie-statementprognoses — deze feature controleert het bestaande deterministische AIMM-oordeel en introduceert geen voorspellend model.
- Nieuwe forensische maatstaven zoals ROIC tegenover WACC, winst-naar-kasstroomconversie, verwatering of schuldlooptijden — inhoudelijke uitbreiding van de analysemethodologie vereist afzonderlijke productspecificaties.
- Automatisch herschrijven of opnieuw berekenen van historische rapporten — historische uitkomsten blijven onveranderd om hun oorspronkelijke context te bewaren.
- Een handmatige uitzondering waarmee een blokkerende bevinding toch de status geverifieerd krijgt — zo'n bypass zou de betekenis van de bewijsstatus ondermijnen.
- Nieuwe gegevensbronnen of veranderingen aan bronprioriteiten — deze feature gebruikt de door het bestaande gegevensbeleid aangeleverde gegevens en beoordeelt hun bewijsdekking.
- AI-gegenereerde beleggingsnarratieven — uitleg binnen deze feature is deterministisch afgeleid van invoer, regels en controles.
- Een samengestelde letter- of cijferscore voor economische aantrekkelijkheid — BUY/HOLD/SELL en rangschikking blijven de bestaande economische uitkomsten; de bewijsstatus beoordeelt uitsluitend de betrouwbaarheid ervan.

## 4. Happy path **[verplicht]**

1. De AIMM-beheerder dient een versieerbare methodologiebasis in en beheert een gegevensbeleid met peerdekking, actualiteitsgrenzen, toleranties en bevindingenclassificatie.
2. De financieel reviewer accepteert de kandidaat-methodologieversie; de geaccepteerde inhoud wordt onveranderlijk en de AIMM-beheerder koppelt deze versie en het actieve gegevensbeleid aan een industriegroep.
3. Het AIMM-verzamelproces levert volgens dat beleid een versieerbare gegevensset met volledige herkomst, waarna de AIMM-datasteward eventuele ontbrekende of conflicterende herkomst uitsluitend via een nieuwe gegevensversie laat herstellen.
4. De belegger start een nieuwe analyse voor die industriegroep.
5. Het systeem toetst de actuele precondities, bepaalt welke ondernemingen, perioden, peers en maatstaven geschikt zijn en bevriest vóór de oorspronkelijke berekening alle gebruikte oorspronkelijke bronwaarden, perioden, eenheden, valuta, bronnen, transformaties, configuratieversies en route-identiteiten in de invoerlaag van het analysemanifest.
6. De oorspronkelijke rekenroute berekent de genormaliseerde invoerwaarden en vervolgens voor iedere geschikte onderneming de peerreferenties, scores, het regelpad, het advies en de rangschikking en voegt alle ongeronde uitkomsten eenmaal toe aan het manifest in opbouw.
7. Het systeem finaliseert het analysemanifest, stelt volgens de vastgelegde manifestrepresentatieversie de inhoudsafgeleide manifestvingerafdruk vast, registreert die vóór publicatie in het append-only integriteitsanker en bindt rapport, manifest-ID, oorspronkelijke uitkomsten en route-identiteiten aan die definitieve inhoud.
8. Het AIMM-verificatieproces herberekent via de functioneel gescheiden verificatieroute normalisaties en analyse-uitkomsten uitsluitend vanuit de bevroren oorspronkelijke invoerlaag en de geaccepteerde methodologie, zonder oorspronkelijke afleidingen te hergebruiken, en vergelijkt beide ongeronde uitkomsten met de vastgelegde toleranties.
9. Het systeem publiceert de verificatie-uitkomst met de status geverifieerd wanneer alle controles zonder waarschuwing of uitsluiting overeenstemmen, of gekwalificeerd wanneer uitsluitend vooraf toegestane niet-blokkerende waarschuwingen of correct verwerkte ondernemingsspecifieke uitsluitingen bestaan; een geblokkeerde uitkomst blijft met haar redenen raadpleegbaar maar publiceert geen economisch advies voor het getroffen bereik.
10. De belegger opent "Waarom dit oordeel?" en volgt per onderneming de keten van bron en transformatie via formule, drempel en score naar het regelpad, advies en rang.
11. De belegger opent de PDF en ziet hetzelfde manifest-ID, dezelfde manifestvingerafdruk, bewijsstatus, dekkingsgraad, peildata, peerselectie, uitsluitingen, waarschuwingen en blokkerende bevindingen als in de webweergave.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| Een onderneming heeft minder dan vijf vergelijkbare afgesloten boekjaren voor een structurele trend | Het systeem sluit die trend uit van adviesvorming, toont de ontbrekende perioden en publiceert voor de onderneming geen advies wanneer daardoor een verplichte kwaliteitscomponent ontbreekt. |
| Eén onderneming is geblokkeerd, maar na uitsluiting blijven alle gedeelde controles en de vereiste peerdekking geldig | De onderneming krijgt geen advies of rang; het rapport krijgt de status gekwalificeerd, herberekent gedeelde uitkomsten zonder de onderneming en toont de uitsluiting en haar gevolgen. |
| Een maatstaf heeft precies drie overige geldige peers | De maatstaf blijft bruikbaar, het rapport toont een lage-peerdekkingswaarschuwing en krijgt ten minste de status gekwalificeerd. |
| Een maatstaf heeft minder dan drie overige geldige peers | De maatstaf vervalt voor de betrokken onderneming; bij minder dan twee resterende bruikbare waarderingsmaatstaven krijgt die onderneming geen advies of rang. |
| Na ondernemingsuitsluitingen blijven minder dan twee ondernemingen met een publiceerbaar advies over | Het rapport krijgt de status geblokkeerd, toont de ontbrekende advies- en peerdekking en publiceert geen rangschikking. |
| Verschillende waarderingsmaatstaven gebruiken door ontbrekende waarden verschillende geldige peers | Het rapport toont per maatstaf de exacte peerset en krijgt de status gekwalificeerd zolang iedere gebruikte maatstaf en onderneming aan de minimumdekking blijft voldoen. |
| Een onderneming is ten onrechte in haar eigen peerreferentie opgenomen of de referentie is geen mediaan | De herberekeningscontrole markeert de methodologieafwijking als blokkerend en toont de betrokken onderneming en maatstaf. |
| Bron, verslagperiode, eenheid, valuta of transformatie van een verplichte invoer ontbreekt of conflicteert | De getroffen berekening en ieder afhankelijk advies worden geblokkeerd; het rapport toont het ontbrekende of conflicterende bewijs zonder een vervangende waarde te verzinnen. |
| Oorspronkelijke berekening en herberekening verschillen buiten de vastgelegde tolerantie | Het rapport krijgt de status geblokkeerd, publiceert geen getroffen advies of rang en toont beide ongeronde waarden, het verschil en de gebruikte tolerantie. |
| Alleen de afgeronde weergave verschilt terwijl de ongeronde waarden binnen tolerantie overeenstemmen | De controle behandelt dit niet als integriteitsfout en vermeldt welke weergaveafronding is toegepast. |
| Een in het manifest vastgelegde bron blijkt na het ophaalmoment niet meer bereikbaar | De herberekening gebruikt uitsluitend het bevroren manifest en doet geen nieuwe bronaanvraag; de latere onbereikbaarheid wordt, wanneer bekend, informatief getoond en verandert volledige historische herkomst niet. |
| Het manifest-ID, de manifestrepresentatieversie, manifestvingerafdruk of het integriteitsanker ontbreekt, of de canonieke inhoud, rapportidentiteit, vingerafdruk en het anker komen niet overeen | Het rapport krijgt de status geblokkeerd, toont de geconstateerde bindingsafwijking en publiceert geen advies of rang. |
| De verificatieroute probeert een oorspronkelijke peerreferentie, score, advies, rang of andere afgeleide uitkomst als controle-invoer te gebruiken | De onafhankelijkheidscontrole wordt rapportblokkerend afgewezen en toont welk verboden afgeleid gegeven werd aangetroffen. |
| Een geconfigureerde onderneming past niet binnen het vereiste niveau van de vastgelegde sectortaxonomie of een maatstafwaarde voldoet niet aan haar versieerbare domeinregel | De onderneming of maatstaf wordt volgens haar functionele bereik uitgesloten, de reden en taxonomie- of maatstafregel blijven zichtbaar en de resterende peerbasis wordt opnieuw beoordeeld. |
| Een verplichte invoer is ouder dan de beleidsdoelstelling maar nog niet ouder dan de harde maximumleeftijd | De invoer blijft bruikbaar, het rapport krijgt de status gekwalificeerd en toont doelstelling, werkelijke leeftijd en harde maximumleeftijd. |
| Een verplichte invoer is ouder dan de harde maximumleeftijd | Het systeem blokkeert de betrokken onderneming of het volledige rapport volgens het bereik van die invoer en toont de overschreden grens. |
| De identiteit van de oorspronkelijke rekenroute of verificatieroute is onvolledig of niet herleidbaar | Het rapport krijgt de status geblokkeerd en toont voor welke route welk release-, routeversie-, revisie-, dependency- of runtimebewijs ontbreekt. |
| De uitgevoerde productcode van een route wijkt af van de vastgelegde bronrevisie en een build-artefactvingerafdruk ontbreekt | Het rapport krijgt de status geblokkeerd en meldt dat de werkelijk uitgevoerde routeversie niet reproduceerbaar is geïdentificeerd. |
| Het gegevensbeleid of de methodologie verandert terwijl een analyse loopt | De lopende analyse behoudt de versies waarmee het manifest is gestart; een volgende analyse gebruikt de nieuwe versies en beide rapporten blijven afzonderlijk reproduceerbaar. |
| Een analyse wordt gelijktijdig of herhaald gestart | Iedere run krijgt een eigen bevroren manifest; runs delen geen tussentijdse context en identieke context produceert een identieke inhoudelijke uitkomst. |
| De analyse of verificatie wordt geannuleerd, loopt buiten haar tijdsgrens of eindigt door een onherstelbare technische fout | De run krijgt de toestand afgebroken, bewaart het beschikbare controlespoor, publiceert geen advies of rang en verandert niet meer van toestand. |
| Een afgebroken analyse wordt opnieuw gestart | De nieuwe poging krijgt een nieuwe runidentiteit en een nieuw manifest-ID met een verwijzing naar de afgebroken voorganger; geen gedeeltelijke manifestinhoud of tussenuitkomst wordt hergebruikt. |
| Een gebruiker probeert een definitief manifest of een definitieve bewijsstatus te wijzigen | Het systeem weigert de wijziging en verlangt een nieuwe analyse voor gecorrigeerde invoer of regels. |
| Een bestaand historisch rapport heeft geen analysemanifest | Het rapport blijft leesbaar, toont de aanduiding historisch niet-geverifieerd en biedt, wanneer de actuele precondities zijn vervuld, een expliciete actie voor een nieuwe analyse die het historische rapport niet wijzigt. |
| Een nieuwe analyse vanuit een historisch rapport kan niet worden gestart | De actie is niet beschikbaar en het historische rapport toont welke actuele gegevens-, peer-, beleids- of methodologievoorwaarde ontbreekt. |
| Alleen een vooraf toegestane waarschuwing, zoals beperkte actualiteit van een niet-blokkerende invoer, is aanwezig | Het rapport krijgt de status gekwalificeerd, toont de waarschuwing en behoudt uitsluitend de adviezen waarvoor alle blokkerende controles zijn geslaagd. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-analysis-integrity1 — Elk nieuw analyserapport bevat een onveranderlijk manifest met manifest-ID, gebruikte ondernemingen, peers, invoerwaarden, afgewezen kandidaat-invoer met reden, perioden, eenheden, valuta, bronnen, transformaties, validatie-uitkomsten, iedere daadwerkelijk berekende oorspronkelijke ongeronde uitkomst, per niet-uitgevoerde berekening een reden, manifestrepresentatieversie, manifestvingerafdruk en effectieve beleids-, methodologie-, rekenroute- en verificatieroute-identiteit.
- AC-analysis-integrity2 — De verificatieroute produceert vanuit uitsluitend de bevroren oorspronkelijke bronwaarden, herkomst, periode-, eenheids- en valutagegevens, transformaties en de geaccepteerde methodologie dezelfde ongeronde normalisaties, peerreferenties, waarderingsverschillen, scores, regelpaden, adviezen en rangschikking binnen tolerantie en weigert iedere oorspronkelijke normalisatie of andere afgeleide uitkomst als controle-invoer.
- AC-analysis-integrity3 — Ieder verschil buiten tolerantie produceert een rapportblokkerende verificatie-uitkomst, blokkeert alle adviezen en de rangschikking en meldt oorspronkelijke waarde, herberekende waarde, verschil en tolerantie.
- AC-analysis-integrity4 — Ieder nieuw rapport toont één bewijsstatus op rapportniveau en per onderneming, met alle waarschuwingen, uitsluitingen en blokkerende bevindingen die die status bepalen.
- AC-analysis-integrity5 — Een structurele trend met minder dan vijf vergelijkbare afgesloten boekjaren blokkeert gebruik van die trend, meldt de ontbrekende of niet-vergelijkbare perioden en toont geen advies of rang voor de onderneming wanneer de geaccepteerde methodologie die trend als verplichte adviescomponent registreert.
- AC-analysis-integrity6 — De peercontrole produceert per waarderingsmaatstaf de mediaan van uitsluitend de overige peers die aan de vastgelegde groeps-, taxonomie-, maatstafdomein-, periode-, eenheids-, valuta- en actualiteitsregels voldoen, vermeldt iedere opname en uitsluiting met reden en toont volledig gedekte, gekwalificeerde of onbruikbare peerdekking bij respectievelijk minimaal vier, precies drie of minder dan drie overige peers.
- AC-analysis-integrity7 — De auditweergave toont per scorecomponent de bronwaarde, verslagperiode, eenheid en valuta, transformatie, formule, drempel, gewicht, scorebijdrage en het afhankelijke regelpad.
- AC-analysis-integrity8 — Een verplichte invoer zonder volledige of consistente herkomstinformatie blokkeert alle afhankelijke uitkomsten en meldt welk bewijselement ontbreekt of conflicteert.
- AC-analysis-integrity9 — Een beleids- of methodologiewijziging tijdens een run registreert geen gemengde context: het lopende rapport behoudt de bij start bevroren versies en een volgende run vermeldt de nieuwe versies.
- AC-analysis-integrity10 — Een herhaalde controle met dezelfde bevroren invoer en dezelfde versies produceert dezelfde ongeronde uitkomsten, bewijsstatus, rang met vast tie-breakgedrag en geordende bevindingen.
- AC-analysis-integrity11 — Een uitgesloten onderneming krijgt geen BUY/HOLD/SELL-oordeel; het rapport toont de geschiktheidsreden in plaats van HOLD.
- AC-analysis-integrity12 — De PDF-uitvoer bevat dezelfde rapportidentiteit, hetzelfde manifest-ID en dezelfde manifestvingerafdruk, bewijsstatus, dekkingsgraad, gegevenspeildata, peerselectie, uitsluitingen, waarschuwingen, blokkerende bevindingen en verificatiesamenvatting als het bijbehorende webrapport.
- AC-analysis-integrity13 — Een historisch rapport zonder manifest toont de aanduiding historisch niet-geverifieerd en weigert een geverifieerde status zonder een nieuwe analyse.
- AC-analysis-integrity14 — Iedere rapportweergave en verificatie controleert de binding tussen rapportidentiteit, manifest-ID, manifestrepresentatieversie, manifestvingerafdruk, integriteitsanker en canonieke manifestinhoud; een afwijking blokkeert ieder advies of rang en toont de bindingsfout, terwijl een definitief manifest en een definitieve bewijsstatus iedere wijziging van invoer, context, oorspronkelijke uitkomst of verificatie-uitkomst weigeren.
- AC-analysis-integrity15 — Een gekwalificeerd rapport vermeldt iedere toegestane waarschuwing en bevat alleen adviezen waarvoor alle blokkerende controles zijn geslaagd.
- AC-analysis-integrity16 — Een ondernemingsspecifieke blokkering toont geen advies of rang voor die onderneming en produceert alleen een gekwalificeerd rapport wanneer de resterende peerbasis en alle gedeelde controles geldig zijn.
- AC-analysis-integrity17 — Een onderneming met minder dan twee bruikbare waarderingsmaatstaven krijgt geen advies of rang en het rapport vermeldt welke maatstaven door onvoldoende peerdekking of invoer zijn vervallen.
- AC-analysis-integrity18 — De verificatie produceert voor geldbedragen, percentages, ratio's, scores en categorische uitkomsten een vergelijking volgens de vastgelegde tolerantietabel, toont referentiewaarde, grens en grensinclusiviteit en blokkeert iedere afwijking daarbuiten of iedere niet-eindige numerieke uitkomst.
- AC-analysis-integrity19 — Een bevinding toont haar vaste klasse informatief, kwalificerend, ondernemingsblokkerend of rapportblokkerend en vermeldt het functionele bereik en de reden.
- AC-analysis-integrity20 — Een historisch niet-geverifieerd rapport toont een actie voor een nieuwe analyse wanneer alle actuele precondities zijn vervuld en meldt anders welke preconditie ontbreekt.
- AC-analysis-integrity21 — Een nieuwe analyse vanuit een historisch rapport produceert een afzonderlijk rapport met actuele context en een verwijzing naar het historische uitgangspunt zonder dat oude rapport te wijzigen.
- AC-analysis-integrity22 — Het manifest bevat voor zowel oorspronkelijke rekenroute als verificatieroute AIMM-release, routeversie, volledige bronrevisie of build-artefactvingerafdruk, dependency-lockvingerafdruk en runtimeversie; ontbrekend of niet-herleidbaar identificatiebewijs produceert een raadpleegbare geblokkeerde verificatie-uitkomst, toont het ontbrekende onderdeel en publiceert geen advies of rang.
- AC-analysis-integrity23 — Een geannuleerde, verlopen of technisch onherstelbaar geëindigde run registreert de toestand afgebroken, bewaart het beschikbare controlespoor en blokkeert advies en rang; een nieuwe poging bevat een nieuwe runidentiteit en een nieuw manifest-ID en vermeldt de voorganger.
- AC-analysis-integrity24 — Een nieuwe analyse gebruikt een bestaand dossierrecord alleen als rekeninvoer wanneer de vereiste herkomst compleet is; anders registreert het manifest het record als afgewezen kandidaat-invoer, blokkeert de geschiktheidscontrole de afhankelijke uitkomst, meldt de ontbrekende herkomst en verlangt een nieuwe gegevensversie zonder het bestaande dossierrecord te herschrijven.
- AC-analysis-integrity25 — Een methodologieversie registreert indiener, financieel reviewer, beslissing en beslismoment; het systeem weigert acceptatie door de indienende AIMM-beheerder en weigert inhoudswijziging na acceptatie.
- AC-analysis-integrity26 — Iedere geldtransformatie toont oorspronkelijke waarde, valuta en eenheid, basisvaluta en basiseenheid, gebruikte wisselkoers met bron en koersdatum, formule en precisie; een ontbrekende, nul- of niet-toegestane koers blokkeert de afhankelijke uitkomst zonder een één-op-éénkoers te gebruiken.
- AC-analysis-integrity27 — Het acceptatierecord van een methodologieversie bevat per tolerantiecategorie de gemotiveerde grens en referentieberekeningen voor iedere ondersteunde runtime- en dependencycombinatie; ontbrekend bewijs blokkeert acceptatie van die versie.
- AC-analysis-integrity28 — De manifestintegriteitscontrole produceert onder dezelfde manifestrepresentatieversie voor dezelfde definitieve inhoud dezelfde manifestvingerafdruk en blokkeert een rapport wanneer enig manifestveld behalve de vingerafdruk zelf wijzigt of de representatieversie onbekend is.
- AC-analysis-integrity29 — Vóór rapportpublicatie bevat een append-only integriteitsanker buiten de wijzigingsroute van manifest en rapport de rapportidentiteit, het manifest-ID, de manifestvingerafdruk en het vastleggingstijdstip; een ontbrekend, gewijzigd of afwijkend anker blokkeert advies en rang en weigert publicatie als geverifieerd of gekwalificeerd.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** gegevensverzameling en herkomstvalidatie, industriegroep- en sectortaxonomiebeheer, methodologiebeheer, industriegroepanalyse, peerselectie, geschiktheidscontrole, score- en adviesvorming, rangschikking, opslag en raadpleging van analyserapporten, de webweergave "Waarom dit oordeel?" en PDF-rapportage.
- **Blokkeert:** implementatie blijft geblokkeerd totdat deze in-review-spec semantisch is beoordeeld en expliciet de status accepted krijgt; ingebruikname van geverifieerde rapporten vereist daarnaast ten minste één door een financieel reviewer geaccepteerde methodologieversie en een gegevensroute die de vereiste herkomst per invoer kan leveren.
- **Blokkeert-door:** toekomstige forensische componenten, scenario- en gevoeligheidsanalyse en automatisch modelreview kunnen het manifest en de verificatie-uitkomst als betrouwbare basis gebruiken.
- **Effect op bestaande data:** bestaande dossierrecords en historische rapporten worden niet herschreven. Voor iedere nieuwe analyse toetst het systeem per kandidaat-invoer of bron, verslagperiode, eenheid, valuta, transformatie en validatie-uitkomst volledig herleidbaar zijn. Een bestaand record dat niet aan dit contract voldoet, wordt met de ontbrekende bewijselementen in het manifest geregistreerd maar niet als rekeninvoer gebruikt; herstel vereist een nieuwe, versieerbare verzameling of correctieversie met werkelijke herkomst. Historische rapporten zonder manifest blijven zichtbaar historisch niet-geverifieerd.
- **Effect op bestaande gebruikers:** beleggers zien naast BUY/HOLD/SELL voortaan een afzonderlijke bewijsstatus, expliciete uitsluitingen, afgebroken runs en een auditroute; onvoldoende bewijs verschijnt niet langer als HOLD. Beheerders dienen kandidaat-methodologieversies in, financieel reviewers beslissen daarover en datastewards herstellen herkomstproblemen uitsluitend via nieuwe gegevensversies. Wijzigingen gelden alleen voor volgende runs.

## 8. Validatie en randvoorwaarden **[optioneel]**

- **Precondities:** de industriegroep heeft een actieve gegevensbeleidsversie en een door een andere actor dan de indiener geaccepteerde methodologieversie; iedere verplichte maatstaf beschrijft formule, invoer, periodebasis, toegestane periodisering, eenheid, richting, waardedomein, sectortaxonomieniveau, minimumdekking, tolerantie, actualiteitsdoel, harde maximumleeftijd en blokkeringsgedrag; iedere gebruikte invoer heeft volledige herkomst; de peerselectie en de identiteit van oorspronkelijke rekenroute en verificatieroute zijn vóór analyse functioneel bepaalbaar.
- **Invariants:** de oorspronkelijke invoerlaag wordt vóór de oorspronkelijke berekening bevroren; de definitieve canonieke manifestinhoud, manifest-ID, manifestrepresentatieversie, manifestvingerafdruk en bewijsstatus veranderen daarna nooit; de verificatieroute vraagt geen actuele externe bron op en gebruikt geen oorspronkelijke normalisatie of andere afgeleide uitkomst als rekeninvoer; weergaveafronding beïnvloedt de controle niet; een geblokkeerde of afgebroken uitkomst levert binnen haar getroffen bereik geen BUY/HOLD/SELL-oordeel of rang; ontbrekende gegevens of wisselkoersen worden nooit geschat of als één-op-éénconversie aangevuld om een controle te laten slagen.
- **Postcondities:** ieder gepubliceerd nieuw rapport is gekoppeld aan precies één definitief manifest en één definitieve bewijsstatus, bevat reproduceerbare verificatiebevindingen en maakt zichtbaar welke ondernemingen en berekeningen wel of niet tot een gepubliceerd advies hebben bijgedragen. Een afgebroken run bewaart een afzonderlijk, raadpleegbaar controlespoor zonder gepubliceerd advies en zonder een definitief rapport te simuleren.

### Manifestintegriteit

- Het manifest-ID wordt bij runstart toegekend en identificeert het manifest ook wanneer de run wordt afgebroken. Alleen een definitief manifest krijgt een manifestvingerafdruk.
- De manifestrepresentatieversie bepaalt de vaste veldvolgorde en de representatie van getallen, datums, tijden, valuta, eenheden, lege en ontbrekende waarden en lijsten. Alle functionele manifestvelden behalve het veld met de manifestvingerafdruk zelf behoren tot de canonieke inhoud; transport- of weergavemetadata mag alleen buiten het manifest blijven.
- De manifestvingerafdruk is botsingsbestendig en uitsluitend afgeleid van de canonieke inhoud. Vóór publicatie bewaart een append-only integriteitsanker buiten de wijzigingsroute van manifest en rapport de rapportidentiteit, het manifest-ID, de manifestvingerafdruk en het vastleggingstijdstip. Rapport en definitieve verificatie-uitkomst verwijzen naar dit anker en bewaren manifest-ID, manifestrepresentatieversie en manifestvingerafdruk; iedere raadpleging en herverificatie controleert deze binding voordat advies of rang zichtbaar wordt.

### Periode- en valutavergelijkbaarheid

- Een structurele trend gebruikt de vijf meest recente opeenvolgende afgesloten perioden vóór de gegevenspeildatum. Iedere periode heeft een werkelijke begin- en einddatum, periodebasis en lengte. De geaccepteerde methodologie bepaalt per trend welke periodebasis en lengtevariatie zijn toegestaan en hoe een 52/53-wekenjaar of gebroken boekjaar wordt genormaliseerd; zonder zo'n regel zijn de betrokken perioden niet vergelijkbaar.
- Een restatement vervangt een eerder cijfer alleen via een nieuwe gegevensversie met publicatie- en ophaaldatum. Het manifest bewaart de daadwerkelijk gebruikte versie; een tijdens de run beschikbaar gekomen restatement beïnvloedt uitsluitend een volgende analyse.
- Een maatstafvergelijking tussen ondernemingen gebruikt dezelfde vastgelegde basis, zoals afgesloten boekjaar, TTM of waarderingssnapshot. Afwijkende boekjaareinden zijn alleen toegestaan binnen de in de methodologie vastgelegde maximale datumspreiding en met een zichtbare periodetransformatie; anders vervalt de maatstaf voor de betrokken peer.
- Voor ieder geldbedrag bewaart het manifest oorspronkelijke waarde, valuta en eenheid én de genormaliseerde waarde, basisvaluta en basiseenheid. Het gegevensbeleid bepaalt de wisselkoersbron, koersdatumregel, maximaal toegestane afstand tot die datum en rekenprecisie. Wisselkoers, bron, koersdatum en formule blijven zichtbaar; een ontbrekende, nul-, niet-eindige of te oude koers blokkeert de afhankelijke uitkomst.
- Tussenafronding is uitsluitend toegestaan wanneer zij als versieerbare transformatie in de methodologie staat. Presentatieafronding vindt pas na analyse en verificatie plaats en verandert nooit de opgeslagen controlewaarden.

### Peerdekking

| Geldige overige peers per onderneming en maatstaf | Functioneel gevolg |
|----------------------------------------------------|--------------------|
| Vier of meer | De peerreferentie voldoet zonder dekkingswaarschuwing. |
| Precies drie | De peerreferentie is bruikbaar en kwalificeert het rapport met een lage-peerdekkingswaarschuwing. |
| Minder dan drie | De maatstaf is onbruikbaar voor de onderneming. |

Ontbrekende peerwaarden raken alleen de betreffende maatstaf. Iedere gebruikte maatstaf bewaart haar exacte peerset, vastgelegde sectortaxonomieversie en uitsluitingsredenen. De methodologie bepaalt per maatstaf of negatieve en nulwaarden geldig zijn en welke vooraf vastgelegde uitzonderingsregel geldt; een waarde wordt nooit alleen wegens haar effect op de mediaan uitgesloten. Verschillende peersets tussen gebruikte maatstaven kwalificeren het rapport. Minder dan twee bruikbare waarderingsmaatstaven sluit de onderneming uit; minder dan twee overblijvende ondernemingen met een publiceerbaar advies blokkeert de rangschikking en het rapport.

### Gelijke rangscores

De rangschikking gebruikt de ongeronde totaalscore. Exact gelijke totaalscores krijgen dezelfde rang volgens competitierangschikking, zodat op rang 1, 1 bijvoorbeeld rang 3 volgt. De canonieke ticker sorteert gelijke ondernemingen uitsluitend voor een stabiele presentatie en verandert hun gedeelde rang niet. Een minieme niet-gelijke score blijft inhoudelijk onderscheidend; wanneer de verificatieroute daardoor een andere rang produceert, geldt de exacte rangvergelijking als blokkerend, ook wanneer het scoreverschil afzonderlijk binnen de numerieke tolerantie ligt.

### Vergelijkingstoleranties

Alle numerieke controles vergelijken genormaliseerde ongeronde waarden. De oorspronkelijke ongeronde uitkomst is de referentiewaarde voor een relatieve grens. De absolute en relatieve grenzen hieronder zijn inclusief; een grotere afwijking is blokkerend. NaN, positieve of negatieve oneindigheid en een niet-numerieke representatie zijn altijd blokkerend. De grenzen drukken technische rekenequivalentie uit en zijn geen economische materialiteitsgrens.

| Uitkomstcategorie | Toegestane afwijking |
|-------------------|-----------------------|
| Geldbedrag in de vastgelegde basisvaluta en basiseenheid | Het grootste van 0,01 basiseenheid en de absolute referentiewaarde vermenigvuldigd met 10^-12. |
| Percentage, yield of waarderingsverschil in procentpunten | Maximaal 10^-7 procentpunt. |
| Ratio, multiple, genormaliseerde score of gewogen score | Maximaal 10^-9 absoluut. |
| Datum, periode, eenheid, valuta, peerset, formuleversie, regelpad, bewijsstatus, advies en rang | Exact gelijk. |
| Afgeronde weergavewaarde | Wordt niet als controle-invoer gebruikt; de onderliggende ongeronde waarde is bepalend. |

Voordat een methodologieversie wordt geaccepteerd, bevat haar controlebewijs per uitkomstcategorie vaste referentievoorbeelden voor exact gelijke waarden en verschillen onder, op en boven de grens. Dezelfde voorbeelden omvatten nul, negatieve waarden, zeer grote waarden en iedere toegestane valuta- of periodetransformatie, zodat representatie en tussenafronding de gekozen grens niet stilzwijgend veranderen. Het acceptatierecord motiveert iedere grens en toont met dezelfde referentieberekeningen voor alle ondersteunde runtime- en dependencycombinaties dat equivalente uitkomsten binnen en opzettelijk gewijzigde uitkomsten buiten de grens vallen; zonder dat bewijs kan de methodologieversie niet worden geaccepteerd.

### Bevindingenclassificatie

| Klasse | Omstandigheden | Functioneel gevolg |
|--------|----------------|--------------------|
| Informatief | Uitsluitend verwachte weergaveafronding of een na het vastgelegde ophaalmoment onbereikbaar geworden bron terwijl het manifest compleet is | Geen wijziging van bewijsstatus of advies; de omstandigheid blijft zichtbaar in het controlespoor. |
| Kwalificerend | Precies drie overige peers, verschillende geldige peersets tussen gebruikte maatstaven, een wegens minder dan drie peers vervallen maatstaf terwijl minimaal twee bruikbare waarderingsmaatstaven overblijven, een verplichte invoer ouder dan de beleidsdoelstelling maar binnen de harde maximumleeftijd, een ontbrekende optionele maatstaf zonder adviesafhankelijkheid, of een correct verwerkte ondernemingsuitsluiting bij geldige resterende peerbasis | Rapportstatus gekwalificeerd; alleen volledig gecontroleerde adviezen blijven zichtbaar. |
| Ondernemingsblokkerend | Minder dan vijf vergelijkbare boekjaren voor een verplichte structurele trend, minder dan twee bruikbare waarderingsmaatstaven, ontbrekende of conflicterende verplichte ondernemingsinvoer of herkomstinformatie, een onderneming buiten het vereiste taxonomieniveau, of een ondernemingsgebonden periode-, eenheid-, valuta- of actualiteitsfout | Geen advies of rang voor de onderneming; het rapport kan alleen gekwalificeerd doorgaan wanneer de resterende peerbasis geldig blijft. |
| Rapportblokkerend | Onvolledig of gewijzigd manifest, ontbrekende of afwijkende manifestbinding, ontbrekende route-identiteit, hergebruik van oorspronkelijke afgeleide uitkomsten door de verificatieroute, onbekende of niet-geaccepteerde beleids- of methodologieversie, replayverschil buiten tolerantie, ongeldige taxonomie- of gedeelde peerbasis, gedeeld periode-, eenheid- of valutaconflict, of minder dan twee overblijvende ondernemingen met een publiceerbaar advies | Geen rapportadvies of rangschikking; alle blokkerende redenen blijven zichtbaar. |

## 9. Toestandsmachine **[optioneel]**

Entiteit: analyseverificatierun op rapportniveau

| Toestand | Betreedbaar vanuit | Exit naar | Actor die transitie triggert |
|----------|--------------------|-----------|--------------------------------|
| In opbouw | — | Geverifieerd, Gekwalificeerd, Geblokkeerd of Afgebroken | AIMM-analyseproces bij de start van een nieuwe analyse |
| Geverifieerd | In opbouw | — | AIMM-verificatieproces nadat geen geconfigureerde onderneming is uitgesloten en alle ondernemings- en gedeelde controles zonder waarschuwing zijn geslaagd |
| Gekwalificeerd | In opbouw | — | AIMM-verificatieproces nadat alle controles voor het gepubliceerde bereik zijn geslaagd en uitsluitend toegestane waarschuwingen of correct verwerkte ondernemingsspecifieke uitsluitingen bestaan |
| Geblokkeerd | In opbouw | — | AIMM-verificatieproces na een gedeelde integriteitsfout, onvoldoende bewijs voor het resterende rapportbereik, een methodologieafwijking of een herberekeningsverschil buiten tolerantie |
| Afgebroken | In opbouw | — | Het actieve AIMM-analyse- of verificatieproces na annulering, overschrijding van de tijdsgrens of een onherstelbare technische fout |

De rapporttoestand aggregeert afzonderlijke ondernemingsuitkomsten. Iedere geconfigureerde onderneming krijgt bij finalisatie een bewijsstatus geverifieerd, gekwalificeerd of geblokkeerd en daarnaast een geschiktheidsuitkomst opgenomen of uitgesloten. Een ondernemingsstatus geblokkeerd leidt tot uitsluiting zonder advies of rang; zij leidt alleen tot een rapportstatus gekwalificeerd wanneer na herberekening de resterende peerbasis en alle gedeelde controles geldig zijn. Een gedeelde blokkerende bevinding leidt altijd tot de rapporttoestand geblokkeerd.

Afgebroken is een operationele toestand en geen bewijsstatus. De run bewaart haar runidentiteit, manifest-ID, beschikbare context, fout- of annuleringsreden en tijdstippen, maar heeft geen definitieve manifestvingerafdruk en publiceert geen advies, rang of schijnbaar volledig manifest. Een nieuwe poging begint altijd als een nieuwe analyseverificatierun met een nieuwe runidentiteit en een nieuw manifest-ID en mag naar de afgebroken voorganger verwijzen.

Verboden overgangen: een definitieve toestand gaat nooit over in een andere toestand of terug naar In opbouw; gewijzigde gegevens, regels of beoordelingsgrenzen en iedere herhaling na Afgebroken vereisen altijd een nieuwe analyseverificatierun.

## 10. Open punten **[optioneel]**

Geen.
