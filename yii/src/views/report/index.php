<?php

declare(strict_types=1);

/**
 * Main report template.
 *
 * This template composes the full report from partials.
 * Used for both PDF generation and HTML preview.
 *
 * @var object $reportData ReportData DTO containing all report information
 * @var yii\web\View $this
 */

/** @var object $company */
$company = $reportData->company;

/** @var object $financials */
$financials = $reportData->financials;

/** @var array $charts */
$charts = $reportData->charts ?? [];

/** @var DateTimeImmutable $generatedAt */
$generatedAt = $reportData->generatedAt;

// Base64 encode logo for reliable PDF rendering
$logoPath = Yii::getAlias('@webroot/images/logo.svg');
if (!file_exists($logoPath)) {
    $logoPath = Yii::getAlias('@webroot/images/logo.png');
}

$logoBase64 = '';
if (file_exists($logoPath)) {
    $extension = pathinfo($logoPath, PATHINFO_EXTENSION);
    $mimeType = $extension === 'svg' ? 'image/svg+xml' : 'image/' . $extension;
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoData);
}
?>
<div class="report">
    <header class="report__header">
        <div class="report__branding">
            <?php if ($logoBase64): ?>
                <img src="<?= $logoBase64 ?>" alt="AIMM Logo" class="report__logo">
            <?php endif; ?>
        </div>
        <h1 class="report__title"><?= htmlspecialchars($company->name ?? 'Company Report', ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="report__subtitle">
            <?= htmlspecialchars($company->industry ?? '', ENT_QUOTES, 'UTF-8') ?>
            <?php if (!empty($company->ticker)): ?>
                | <?= htmlspecialchars($company->ticker, ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            | Analysis as of <?= $generatedAt->format('F j, Y') ?>
        </p>

        <div class="report__meta">
            <?php if (!empty($company->ticker)): ?>
            <div class="report__meta-item">
                <strong>Ticker:</strong>
                <span><?= htmlspecialchars($company->ticker, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($company->industry)): ?>
            <div class="report__meta-item">
                <strong>Industry:</strong>
                <span><?= htmlspecialchars($company->industry, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($reportData->peerGroup->name)): ?>
            <div class="report__meta-item">
                <strong>Peer Group:</strong>
                <span><?= htmlspecialchars($reportData->peerGroup->name, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?= $this->render('partials/_financials', ['financials' => $financials]) ?>

    <?php if (!empty($charts)): ?>
        <?= $this->render('partials/_charts', ['charts' => $charts]) ?>
    <?php endif; ?>
</div>
