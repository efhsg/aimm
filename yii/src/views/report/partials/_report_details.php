<?php

declare(strict_types=1);

use app\dto\pdf\RankingMetadataDto;

/**
 * Report details section for ranking PDF.
 *
 * @var RankingMetadataDto $metadata
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Report Details</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Companies Analyzed</span>
            <span class="detail-value"><?= $metadata->companyCount ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Generated At</span>
            <span class="detail-value"><?= htmlspecialchars($metadata->generatedAt, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Data As Of</span>
            <span class="detail-value"><?= htmlspecialchars($metadata->dataAsOf, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Report ID</span>
            <span class="detail-value detail-value--mono"><?= htmlspecialchars($metadata->reportId, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
</section>
