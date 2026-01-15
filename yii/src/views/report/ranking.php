<?php

declare(strict_types=1);

use app\dto\pdf\RankingReportData;

/**
 * Ranking report template for PDF generation.
 *
 * Displays all companies ranked, matching the web ranking page.
 *
 * @var yii\web\View $this
 * @var RankingReportData $reportData
 */
?>
<div class="report">
    <header class="report__header">
        <h1 class="report__title">Rankings - <?= htmlspecialchars($reportData->industryName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="report__subtitle">
            Analysis as of <?= $reportData->generatedAt->format('F j, Y') ?>
        </p>
    </header>

    <?= $this->render('partials/_report_details', ['metadata' => $reportData->metadata]) ?>

    <?= $this->render('partials/_industry_averages', ['averages' => $reportData->groupAverages]) ?>

    <?= $this->render('partials/_ranking_table', ['rankings' => $reportData->companyRankings]) ?>

    <?= $this->render('partials/_rating_summary', ['rankings' => $reportData->companyRankings]) ?>
</div>
