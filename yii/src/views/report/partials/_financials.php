<?php

declare(strict_types=1);

/**
 * Financial summary table partial.
 *
 * Displays key financial metrics with YoY changes and peer comparisons.
 *
 * @var object $financials Financials DTO with metrics array
 */

$metrics = $financials->metrics ?? [];

if (empty($metrics)) {
    return;
}
?>
<section class="report__section">
    <h2 class="report__section-title">Financial Summary</h2>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-label">Metric</th>
                <th class="col-numeric col-amount">Latest</th>
                <th class="col-numeric col-percent">YoY Change</th>
                <th class="col-numeric col-amount">Peer Avg</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metrics as $metric): ?>
            <tr>
                <td><?= htmlspecialchars($metric->label ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="col-numeric"><?= $this->render('_metric_value', ['metric' => $metric]) ?></td>
                <td class="col-numeric <?= $this->render('_change_class', ['change' => $metric->change ?? null]) ?>">
                    <?= $this->render('_metric_change', ['metric' => $metric]) ?>
                </td>
                <td class="col-numeric"><?= $this->render('_metric_peer', ['metric' => $metric]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
