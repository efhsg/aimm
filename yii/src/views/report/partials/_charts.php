<?php

declare(strict_types=1);

/**
 * Charts section partial.
 *
 * Displays chart images from the analytics service.
 * Charts are referenced by relative path (charts/{id}.png) which resolves
 * correctly in the PDF RenderBundle context where assets are bundled together.
 *
 * NOTE: For web preview, charts array is empty by default. If charts are
 * enabled for preview in the future, the ReportController must either:
 * - Serve chart images via a dedicated route, or
 * - Inline charts as base64 data URIs
 *
 * @var array $charts Array of ChartDto objects
 */

if (empty($charts)) {
    return;
}
?>
<section class="report__section">
    <h2 class="report__section-title">Visual Analysis</h2>

    <?php foreach ($charts as $chart): ?>
    <figure class="report__figure avoid-break">
        <?php if ($chart->available ?? false): ?>
        <img
            src="charts/<?= htmlspecialchars($chart->id ?? 'unknown', ENT_QUOTES, 'UTF-8') ?>.png"
            alt="<?= htmlspecialchars(ucfirst($chart->type ?? 'chart'), ENT_QUOTES, 'UTF-8') ?> chart"
            class="report__chart"
            width="<?= (int) ($chart->width ?? 800) ?>"
            height="<?= (int) ($chart->height ?? 400) ?>"
        >
        <?php else: ?>
        <div class="report__chart-placeholder">
            <?= htmlspecialchars($chart->placeholderMessage ?? 'Chart not available', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($chart->caption)): ?>
        <figcaption class="report__figcaption">
            <?= htmlspecialchars($chart->caption, ENT_QUOTES, 'UTF-8') ?>
        </figcaption>
        <?php endif; ?>
    </figure>
    <?php endforeach; ?>
</section>
