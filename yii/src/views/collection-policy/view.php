<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed> $policy
 */

$this->title = $policy['name'];

$formatJson = function (mixed $value): string {
    if ($value === null || $value === '') {
        return '-';
    }
    if (is_string($value)) {
        $decoded = json_decode($value);
        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return $value;
    }
    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    return '-';
};
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['update', 'slug' => $policy['slug']]) ?>" class="btn btn--primary">
            Edit
        </a>
        <a href="<?= Url::to(['export', 'slug' => $policy['slug']]) ?>" class="btn btn--secondary">
            Export JSON
        </a>
        <form method="post"
              action="<?= Url::to(['delete', 'slug' => $policy['slug']]) ?>"
              onsubmit="return confirm('Delete this collection policy?');">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>
            <button type="submit" class="btn btn--danger">
                Delete
            </button>
        </form>
        <a href="<?= Url::to(['index']) ?>" class="btn btn--secondary">
            Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <h2 class="card__title">Details</h2>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Slug</div>
            <div class="detail-value"><code><?= Html::encode($policy['slug']) ?></code></div>

            <div class="detail-label">Description</div>
            <div class="detail-value">
                <?= !empty($policy['description']) ? Html::encode($policy['description']) : '<span class="text-muted">No description</span>' ?>
            </div>

            <div class="detail-label">History Years</div>
            <div class="detail-value"><?= $policy['history_years'] ?></div>

            <div class="detail-label">Quarters to Fetch</div>
            <div class="detail-value"><?= $policy['quarters_to_fetch'] ?></div>

            <div class="detail-label">Created</div>
            <div class="detail-value">
                <?= Html::encode($policy['created_at']) ?>
                <?php if (!empty($policy['created_by'])): ?>
                    by <?= Html::encode($policy['created_by']) ?>
                <?php endif; ?>
            </div>

            <div class="detail-label">Updated</div>
            <div class="detail-value"><?= Html::encode($policy['updated_at']) ?></div>
        </div>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Macro Requirements</h2>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Commodity Benchmark</div>
            <div class="detail-value">
                <?= !empty($policy['commodity_benchmark']) ? Html::encode($policy['commodity_benchmark']) : '<span class="text-muted">-</span>' ?>
            </div>

            <div class="detail-label">Margin Proxy</div>
            <div class="detail-value">
                <?= !empty($policy['margin_proxy']) ? Html::encode($policy['margin_proxy']) : '<span class="text-muted">-</span>' ?>
            </div>

            <div class="detail-label">Sector Index</div>
            <div class="detail-value">
                <?= !empty($policy['sector_index']) ? Html::encode($policy['sector_index']) : '<span class="text-muted">-</span>' ?>
            </div>

            <div class="detail-label">Required Indicators</div>
            <div class="detail-value">
                <?php $ri = $formatJson($policy['required_indicators']); ?>
                <?php if ($ri !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($ri) ?></pre>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </div>

            <div class="detail-label">Optional Indicators</div>
            <div class="detail-value">
                <?php $oi = $formatJson($policy['optional_indicators']); ?>
                <?php if ($oi !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($oi) ?></pre>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$sourcePriorities = null;
if (!empty($policy['source_priorities'])) {
    $sourcePriorities = is_string($policy['source_priorities'])
        ? json_decode($policy['source_priorities'], true)
        : $policy['source_priorities'];
}
$categoryLabels = [
    'valuation' => 'Valuation',
    'financials' => 'Financials',
    'quarters' => 'Quarterly Data',
    'macro' => 'Macro Indicators',
    'benchmarks' => 'Benchmarks',
];
?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Source Priorities</h2>
    </div>
    <div class="card__body">
        <?php if (empty($sourcePriorities)): ?>
            <p class="text-muted">No source priorities configured.</p>
        <?php else: ?>
            <div class="detail-grid">
                <?php foreach ($sourcePriorities as $category => $sources): ?>
                    <div class="detail-label"><?= Html::encode($categoryLabels[$category] ?? ucfirst($category)) ?></div>
                    <div class="detail-value">
                        <?php if (empty($sources)): ?>
                            <span class="text-muted">-</span>
                        <?php else: ?>
                            <div class="source-priority-list">
                                <?php foreach ($sources as $index => $sourceId): ?>
                                    <a href="<?= Url::to(['/data-source/view', 'id' => $sourceId]) ?>"
                                       class="badge badge--info">
                                        <?= Html::encode($sourceId) ?>
                                    </a>
                                    <?php if ($index < count($sources) - 1): ?>
                                        <span class="source-priority-list__arrow">&rarr;</span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-muted text-sm source-priority-list__note">
                Sources are tried in order (left to right) until data is found.
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Data Requirements</h2>
    </div>
    <div class="card__body">
        <div class="metrics-grid">
            <div class="metrics-section">
                <h3>Valuation Metrics</h3>
                <?php $vm = $formatJson($policy['valuation_metrics']); ?>
                <?php if ($vm !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($vm) ?></pre>
                <?php else: ?>
                    <span class="text-muted">Not configured</span>
                <?php endif; ?>
            </div>

            <div class="metrics-section">
                <h3>Annual Financial Metrics</h3>
                <?php $afm = $formatJson($policy['annual_financial_metrics']); ?>
                <?php if ($afm !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($afm) ?></pre>
                <?php else: ?>
                    <span class="text-muted">Not configured</span>
                <?php endif; ?>
            </div>

            <div class="metrics-section">
                <h3>Quarterly Financial Metrics</h3>
                <?php $qfm = $formatJson($policy['quarterly_financial_metrics']); ?>
                <?php if ($qfm !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($qfm) ?></pre>
                <?php else: ?>
                    <span class="text-muted">Not configured</span>
                <?php endif; ?>
            </div>

            <div class="metrics-section">
                <h3>Operational Metrics</h3>
                <?php $om = $formatJson($policy['operational_metrics']); ?>
                <?php if ($om !== '-'): ?>
                    <pre class="json-display"><?= Html::encode($om) ?></pre>
                <?php else: ?>
                    <span class="text-muted">Not configured</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
