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
              style="display: inline;"
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

            <div class="detail-label">Sector Default</div>
            <div class="detail-value">
                <?php if (!empty($policy['is_default_for_sector'])): ?>
                    <span class="badge badge--info"><?= Html::encode($policy['is_default_for_sector']) ?></span>
                    <form method="post"
                          action="<?= Url::to(['set-default', 'slug' => $policy['slug']]) ?>"
                          style="display: inline; margin-left: var(--space-2);">
                        <?= Html::hiddenInput(
                            Yii::$app->request->csrfParam,
                            Yii::$app->request->csrfToken
                        ) ?>
                        <input type="hidden" name="sector" value="<?= Html::encode($policy['is_default_for_sector']) ?>">
                        <input type="hidden" name="clear" value="1">
                        <button type="submit" class="btn btn--sm btn--secondary">
                            Clear
                        </button>
                    </form>
                <?php else: ?>
                    <span class="text-muted">Not set</span>
                <?php endif; ?>
            </div>

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

<div class="card" style="margin-top: var(--space-6);">
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

<div class="card" style="margin-top: var(--space-6);">
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

<style>
code {
    font-family: var(--font-mono);
    background: var(--bg-subtle);
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius-sm);
}
.json-display {
    font-family: var(--font-mono);
    font-size: var(--text-sm);
    background: var(--bg-subtle);
    padding: var(--space-3);
    border-radius: var(--radius-md);
    overflow-x: auto;
    margin: 0;
    white-space: pre-wrap;
}
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-6);
}
.metrics-section h3 {
    font-size: var(--text-base);
    font-weight: 600;
    margin-bottom: var(--space-3);
}
</style>
