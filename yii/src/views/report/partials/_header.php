<?php

declare(strict_types=1);

/**
 * PDF page header template.
 *
 * Rendered by Gotenberg on each page.
 * Uses Gotenberg's built-in page number placeholders.
 *
 * @var object $reportData Report data with company information
 */

$companyName = $reportData->company->name ?? 'Company Report';
?>
<header style="font-size: 9pt; color: #5a7a88; padding: 5mm 15mm; border-bottom: 1px solid #d0dce0; display: flex; justify-content: space-between; align-items: center;">
    <span style="font-weight: 500;"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></span>
    <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
</header>
