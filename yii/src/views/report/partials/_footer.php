<?php

declare(strict_types=1);

/**
 * PDF page footer template.
 *
 * Rendered by Gotenberg on each page.
 *
 * @var object $reportData Report data with generation timestamp
 */

$generatedAt = $reportData->generatedAt ?? new DateTimeImmutable();
$formattedDate = $generatedAt->format('Y-m-d H:i') . ' UTC';

$cssPath = Yii::getAlias('@webroot/css/report.css');
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
// Fix font paths for Gotenberg bundle structure
$css = str_replace('../fonts/', 'assets/fonts/', $css);
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        <?= $css ?>
    </style>
</head>
<body>
    <footer class="pdf-footer">
        <span>Generated <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></span>
        <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
    </footer>
</body>
</html>
