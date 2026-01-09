<?php

declare(strict_types=1);

/**
 * PDF report layout template.
 *
 * This layout is used for PDF generation via Gotenberg.
 * It produces a self-contained HTML document with no external resources.
 *
 * @var string $content The main content to render
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Report</title>
    <link rel="stylesheet" href="assets/report.css">
</head>
<body>
    <?= $content ?>
</body>
</html>
