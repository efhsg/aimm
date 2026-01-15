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
<!DOCTYPE html>
<html>
<head>
    <style>
        body { margin: 0; padding: 0; }
        footer {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 8pt;
            color: #8a9da6;
            padding: 5mm 15mm;
            text-align: center;
            border-top: 1px solid #e6eef0;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
    <footer>
        <span>Generated <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></span>
        <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
    </footer>
</body>
</html>
