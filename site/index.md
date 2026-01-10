---
layout: home
hero:
  name: AIMM
  text: Equity Intelligence Pipeline
  tagline: Data quality over speed
  actions:
    - theme: brand
      text: Setup
      link: /setup
    - theme: brand
      text: Pipeline
      link: /pipeline
    - theme: alt
      text: Architecture
      link: /architecture
    - theme: alt
      text: CLI Usage
      link: /cli-usage
features:
  - title: Phase 1 - Collect
    details: Gather financial data for an entire industry into a persistent Company Dossier.
  - title: Phase 2 - Analyze
    details: Deterministic calculations comparing companies within an industry set.
  - title: Phase 3 - Render
    details: Generate institutional-grade PDF reports from analyzed data.
---

# Overview

AIMM is a three-phase pipeline that generates institutional-grade equity research PDF reports for publicly traded companies. The system collects financial data for an entire industry, analyzes company data against group averages, and renders a professional PDF report.

## Core Principle

**Data quality over speed.** Every datapoint must carry full provenance (source URL, retrieval timestamp, extraction method). Validation gates between phases prevent "beautiful but wrong" reports.

## At-a-Glance

| Phase | Input | Output |
|-------|-------|--------|
| Collect | Industry config (DB) | Company Dossier (DB) + Collection Run |
| Analyze | Dossier data (industry-wide) | RankedReportDTO JSON (stored in DB) |
| Render | Report ID (DB) | PDF report (stored file) |

## Terminology

### Industry vs Sector

- **industry_id**: The machine identifier used as the primary pipeline key (CLI args, file names, artifact folders). Example: `integrated_oil_gas`
- **Industry**: The company set + macro requirements defined by a single industry config
- **Sector**: A coarse taxonomy label (e.g., `Energy`) stored inside an industry config for grouping/filtering

::: info Important
In Phase 1, collection always starts from an `industry_id`. "Collect company info for a sector" means collecting for one industry within that sector.
:::
