# Deep Investigation: PDF Generation Solution for AIMM

> Status: Decision made — AIMM uses Gotenberg for HTML-to-PDF; the Python renderer has been removed. This prompt is
> retained for historical context.

## Persona
**Role:** Senior Principal Software Architect & Technical Lead
**Core Attributes:**
- **Pragmatic:** Balances "perfect" purity with business constraints and delivery timelines.
- **Visual Perfectionist:** Understands that for financial reports, typography and layout credibility are as important as data accuracy.
- **System Thinker:** Evaluates architectural decisions not just on code, but on infrastructure, maintenance, and team skillset.
- **Reference-Driven:** Heavily weighs existing internal success stories (like the `lvs-bes` solution) to reduce R&D risk.

## Intent
This investigation informed the decision to use HTML-to-PDF (Gotenberg) for AIMM. The goal remains to maximize visual
quality ("institutional-grade") while minimizing the implementation friction of complex layouts.

## Context
**Project:** AIMM (AI Investment Model Matrix)
**Goal:** Generate "institutional-grade" equity research PDF reports.
**Current Architecture:**
- **Stack:** PHP (Yii2) orchestrator + Gotenberg (Chromium HTML-to-PDF).
- **Data Flow:** PHP collects/analyzes data -> Renders HTML/CSS + assets -> Gotenberg renders PDF.
- **Charts:** Generated as HTML/CSS or pre-rendered images from DTO data.

## The Challenge
We need to ensure the PDFs are:
1.  **Professional & Branded:** Pixel-perfect layout, consistent fonts/colors, high-quality typography.
2.  **Maintainable:** Easy to update layouts and styles (templating engine).
3.  **Robust:** Can handle dynamic content lengths (text wrapping, page breaks) gracefully.

## Reference Solution (lvs-bes)
We have access to a reference implementation in another project (`lvs-bes`) that uses a different approach:
- **Stack:** PHP (Yii2).
- **Mechanism:** HTML-to-PDF using a Headless Browser (likely Chrome/Puppeteer via `tremaniext\PdfRenderer`).
- **Templating:** Uses standard Yii2 Views (`.php` files) and CSS for layout.
- **Orchestration:**
    ```php
    // Application uses a Factory to get a Renderer (Local or Remote Browser)
    $renderer = (new PdfRendererFactory())->getPdfRenderer();
    
    // Renders a Yii View to HTML string
    $html = $this->renderFile($layout, ['content' => $content]);
    
    // Generates PDF from HTML
    return $renderer->generatePdf($html);
    ```

## Task
Act as a Senior Software Architect. Conduct a deep investigation to determine the best technical path for AIMM's PDF generation.

### 1. Analyze the Trade-offs
Compare **Option A (Current Plan: Python/ReportLab)** vs. **Option B (Reference: HTML-to-PDF)**.
- **Templating:** ReportLab (programmatic layout) vs. HTML/CSS (declarative layout).
- **Charts:** Matplotlib (Python) vs. JS Charts (Highcharts/Chart.js) or Server-side chart generation in HTML.
- **Typography:** Kerning, hyphenation, font handling in ReportLab vs. Browser engine.
- **Infrastructure:** Python container vs. Headless Chrome container.

### 2. Evaluate the "lvs-bes" Fit
- Is the `tremaniext\PdfRenderer` approach (or similar) suitable for financial reports?
- How does it handle page breaks in complex reports (e.g., preventing a table row from splitting)?
- How would we integrate the charts? (Note: We currently have logic to generate charts in Python. If we switch to HTML, do we embed chart images? Or switch to JS charts rendered by the headless browser?)

### 3. Recommendation
Propose the "Best" solution.
- **If we stick with Python:** How do we solve the "templating" problem? Is there a library that makes layouts easier than raw ReportLab canvas commands?
- **If we switch to HTML-to-PDF:** specifically how should we architect it?
    - Which library/service? (e.g., `spatie/browsershot`, `gotenberg`, or the `tremaniext` custom solution?)
    - How do we handle the "Analysis -> Report" handoff?
    - How do we handle charts?

### 4. Implementation Plan
Provide a high-level step-by-step plan for the recommended solution.

## Constraints
- The solution must support strictly defined peer groups and financial tables.
- Visual quality is paramount (must look like a Goldman Sachs/Morgan Stanley report).
- Gotenberg is the supported PDF renderer in `docker-compose.yml`.
