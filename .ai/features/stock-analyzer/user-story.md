# User story — Stock Analyzer (8 Pillars + DCF)

> Vastgelegd tijdens strategische intake (2026-07-30). Ook beschikbaar als AI Note #618.
> Vervolgartefact: `spec.md` in deze map (PRD, gevalideerd R1–R12).

## Epic

Als belegger wil ik een eigen, lokaal draaiende stock analyzer die per ticker de 8 Pillars toetst en een interactieve DCF-waardering biedt, zodat ik zonder $70/maand-abonnement gefundeerde koopbeslissingen neem op basis van eigen formules en eigen data.

**Kleinste sterke versie (Definition of Done van het epic):** één ticker (AAPL) end-to-end — Pillars-oordeel en DCF-scenario's werkend op lokaal gebufferde FMP-data, in een Docker Compose-stack op localhost.

## Story 1 — 8 Pillars-check

Als belegger wil ik één ticker invoeren en direct zien welke van de 8 Pillars het bedrijf haalt over minimaal 5 jaar historie, zodat ik in seconden een gefundeerd eerste oordeel heb.

**Acceptatiecriteria**

- Ticker-invoer accepteert US- en internationale FMP-tickers (AAPL, ASML).
- Het dashboard toont per pillar: pass/fail, de onderliggende waarde(n) en de gehanteerde drempel.
- Default-drempels volgen de canonieke Everything Money-definities; per pillar configureerbaar via config, zonder aparte beheer-UI.
- Bij minder dan 5 jaar historie toont de pillar de status "onvoldoende data" in plaats van fail.
- Herhaalde opvraging van dezelfde ticker kost geen nieuwe FMP-calls voor onveranderlijke periodes (buffer).

## Story 2 — Interactieve DCF-calculator

Als belegger wil ik per scenario (laag/midden/hoog) de FCF-groei, terminal multiple en discount rate instellen met sliders en direct de Fair Value per aandeel zien, zodat ik een maximale koopprijs bepaal die bij mijn rendementseis past.

**Acceptatiecriteria**

- Drie scenario's naast elkaar, elk met eigen sliders voor groei, multiple en discount rate.
- Herberekening gebeurt client-side, zonder server-roundtrip, direct bij elke slider-beweging.
- Startwaarden (TTM free cash flow, uitstaande aandelen, actuele koers) komen uit de datalaag.
- Per scenario toont de app de Fair Value per aandeel en de marge ten opzichte van de actuele koers.
- De berekening volgt de DCF-formule uit de architectuurbrief: 10 jaar verdisconteerde FCF plus verdisconteerde terminal value, gedeeld door uitstaande aandelen.

## Story 3 — Lokaal databezit

Als belegger wil ik dat opgehaalde jaarcijfers permanent lokaal opgeslagen worden, zodat mijn analyses binnen de gratis FMP-quota blijven werken en onafhankelijk zijn van FMP-beleidswijzigingen.

**Acceptatiecriteria**

- Afgesloten boekjaren en kwartalen worden zonder vervaltermijn opgeslagen in SQLite (read-through buffer).
- Vluchtige data (koers, TTM-cijfers) krijgt een TTL.
- Een backfill-run met een (tijdelijke) Starter-key haalt per ticker uit een lijst de volledige beschikbare historie op.
- Rate limiting bewaakt de 250 calls/dag-limiet (patroon uit aimm hergebruiken).

## Buiten scope

Wijzigingen aan aimm; SEC EDGAR/XBRL; Yahoo Finance; deployment, auth, multi-user; watchlists, alerts, BUY/HOLD/SELL-advies, PDF-rapporten; beheer-UI voor de buffer; Python.

## Aannames en open punten

- FMP gratis tier levert ≥5 jaar annual data — verifieer met de bestaande aimm-key (`income-statement?limit=10`).
- FMP `key-metrics` levert ROIC direct; anders afgeleide berekening uit statements.
- De aimm FMP-key is herbruikbaar.
- FMP-terms staan lokale retentie voor persoonlijk gebruik toe — check bij de backfill-route.

## Technisch kader (uit strategische intake)

Nieuwe Next.js/TypeScript-app (App Router, Tailwind + shadcn/ui, Recharts); FMP-datalaag in TypeScript geport uit aimm's FmpAdapter (endpoint-contracten, veldmappings, cache/rate-limit-patronen, fixtures als contract-tests); SQLite read-through buffer; Docker Compose, localhost, single user, FMP-key in `.env`. Aanbevolen vervolg: spec + plan als founding documents in de nieuwe repo, aangestuurd vanuit PromptManager Project 12.
