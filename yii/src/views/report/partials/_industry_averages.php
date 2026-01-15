<?php

declare(strict_types=1);

use app\dto\pdf\GroupAveragesDto;

/**
 * Industry averages section for ranking PDF.
 *
 * @var GroupAveragesDto $averages
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Industry Averages</h2>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Fwd P/E</span>
            <span class="detail-value"><?= $averages->formatFwdPe() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">EV/EBITDA</span>
            <span class="detail-value"><?= $averages->formatEvEbitda() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">FCF Yield</span>
            <span class="detail-value"><?= $averages->formatFcfYield() ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Div Yield</span>
            <span class="detail-value"><?= $averages->formatDivYield() ?></span>
        </div>
    </div>
</section>
