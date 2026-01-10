<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * PDF report layout template.
 *
 * This layout is used for PDF generation via Gotenberg.
 * It produces a self-contained HTML document with no external resources.
 *
 * @var string $content The main content to render
 */

$cssPath = Yii::getAlias('@webroot/css/report.css');
$css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
// Fix font paths for Gotenberg bundle structure
$css = str_replace('../fonts/', 'assets/fonts/', $css);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Report</title>
    <style>
        <?= $css ?>
    </style>
</head>
<body>
    <?= $content ?>
</body>
</html>
