# Functionele specificatie: AIMM veilige branchstart

**Feature-map:** `.ai/features/aimm-new-branch/`
**Status:** accepted
**Contractrevisie:** AIMM-PRD-1
**Feature-slug:** aimmnewbranch
**Aangemaakt:** 2026-08-05

---

## 1. Doel en context **[verplicht]**

**Kernbericht (één zin):** De AIMM-ontwikkelaar kan een nieuwe taakbranch starten vanaf een expliciet bevestigde basis zonder fetch, pull of push stilzwijgend mee te autoriseren.

De bestaande branchskill combineert lokale branchcreatie met netwerkmutaties. Dat botst met AIMM’s regel dat commit en push afzonderlijk moeten worden gevraagd en maakt herstel moeilijker wanneer de remote of uitgangsbranch onverwacht afwijkt.

De geoptimaliseerde flow scheidt read-only inventarisatie, lokale branchcreatie en remote publicatie. Bestaande gebruikerswijzigingen blijven behouden en iedere netwerkactie vereist een expliciete keuze.

## 2. Actoren **[verplicht]**

| Actor | Rol | Toegang / recht |
|-------|-----|-----------------|
| AIMM-ontwikkelaar | Kiest taaktype, naam, basis en toegestane mutaties | Beslisser |
| Branch-agent | Inventariseert Git-status en voert alleen bevestigde acties uit | Uitvoerder binnen de AIMM-worktree |
| Git-remote | Optionele bron of publicatiedoel | Geen impliciet mutatierecht |

## 3. Scope **[verplicht]**

### In scope

- Read-only controle van actieve worktree, branch, status, HEAD en beschikbare lokale basisrefs.
- Generatie en validatie van een AIMM-conforme branchnaam.
- Expliciete keuze van de basis voor lokale branchcreatie.
- Afzonderlijke toestemming voor fetch en voor publicatie naar een remote.
- Readback van branch, HEAD, upstream en worktreestatus na iedere uitgevoerde stap.

### Niet in scope

- Automatisch pullen, rebasen of mergen — zulke geschiedeniswijzigingen vereisen een aparte workflow.
- De nieuwe branch automatisch pushen — publicatie is een afzonderlijk besluit.
- Bestaande wijzigingen verwijderen of stashen — gebruikerswerk blijft onaangeraakt.
- Een branch maken in een andere worktree — alleen de expliciet toegewezen AIMM-worktree valt binnen scope.

## 4. Happy path **[verplicht]**

1. De ontwikkelaar start de branchflow in de toegewezen AIMM-worktree.
2. De agent toont de actuele branch, HEAD, worktreestatus en beschikbare basis zonder een netwerkactie uit te voeren.
3. De ontwikkelaar bevestigt taaktype, beschrijving en lokale basis en kiest afzonderlijk of een fetch nodig is.
4. De agent maakt de lokale branch en leest branch, HEAD en behouden worktreestatus terug.
5. De agent vraagt alleen op expliciet verzoek afzonderlijke toestemming om een upstream te publiceren.

## 5. Edge cases **[verplicht]**

| Situatie | Functioneel gewenst gedrag |
|----------|----------------------------|
| De worktree bevat bestaande wijzigingen | De agent toont ze en vraagt bevestiging voordat dezelfde wijzigingen op de nieuwe branch worden meegenomen. |
| De gewenste branchnaam bestaat al | De flow weigert overschrijven en vraagt een andere naam of expliciete stop. |
| De gekozen basisref ontbreekt lokaal | De agent meldt dit en vraagt afzonderlijk toestemming voor een gerichte fetch. |
| De gebruiker weigert netwerktoegang | De flow gebruikt alleen aantoonbaar beschikbare lokale refs en voert geen fetch of push uit. |
| Branchcreatie slaagt maar publicatie faalt | De lokale branch blijft als herstelanker bestaan en de agent voert geen pull, rebase of retry uit zonder nieuw besluit. |

## 6. Acceptatiecriteria **[verplicht]**

- AC-aimmnewbranch1 — De branchflow toont vóór mutatie de actieve AIMM-root, branch, HEAD en worktreestatus.
- AC-aimmnewbranch2 — Lokale branchcreatie produceert een AIMM-conforme unieke branchnaam vanaf de expliciet bevestigde basis.
- AC-aimmnewbranch3 — De flow weigert fetch, pull, rebase, merge of push uit te voeren zonder passende expliciete autorisatie.
- AC-aimmnewbranch4 — De readback toont dat bestaande worktreewijzigingen na branchcreatie behouden en opnieuw zichtbaar zijn.
- AC-aimmnewbranch5 — Een mislukte publicatie meldt de lokale branch als herstelanker en voert geen automatische geschiedeniswijziging uit.

## 7. Impact op bestaande flows en data **[verplicht]**

- **Raakt:** de bestaande AIMM `new-branch`-command en skill en de start van feature-, fix-, refactor- en chorewerk.
- **Blokkeert:** branchcreatie wacht op een expliciete basis- en scopekeuze wanneer worktree of ref ambigu is.
- **Blokkeert-door:** implementatie kan na lokale branchreadback starten; remote publicatie blijft optioneel.
- **Effect op bestaande data:** Git-referenties kunnen na goedkeuring wijzigen; bestanden, productdata en bestaande wijzigingen blijven behouden.
- **Effect op bestaande gebruikers:** ontwikkelaars krijgen voorspelbare lokale branchcreatie zonder onverwachte netwerk- of geschiedenisoperaties.
