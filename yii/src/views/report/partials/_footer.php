<?php

declare(strict_types=1);

/**
 * PDF page footer template.
 *
 * Rendered by Gotenberg on each page.
 *
 * @var object $reportData Report data with generation timestamp
 */

$generatedAt = $reportData->generatedAt ?? new \DateTimeImmutable();
$formattedDate = $generatedAt->format('Y-m-d H:i') . ' UTC';
?>
<footer style="font-size: 8pt; color: #8a9da6; padding: 5mm 15mm; text-align: center; border-top: 1px solid #e6eef0;">
    Generated <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?> | Confidential - For Internal Use Only
</footer>
