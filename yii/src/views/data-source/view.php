<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed> $dataSource
 * @var array<string, mixed>[] $usingPolicies
 */

$this->title = $dataSource['name'];

$typeLabels = [
    'api' => 'API',
    'web_scrape' => 'Web Scrape',
    'derived' => 'Derived',
];
$typeClass = [
    'api' => 'badge--info',
    'web_scrape' => 'badge--warning',
    'derived' => 'badge--secondary',
];
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['update', 'id' => $dataSource['id']]) ?>" class="btn btn--secondary">
            Edit
        </a>
        <form method="post" action="<?= Url::to(['toggle', 'id' => $dataSource['id']]) ?>" style="display: inline;">
            <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
            <input type="hidden" name="return_url" value="<?= Url::to(['view', 'id' => $dataSource['id']]) ?>">
            <button type="submit" class="btn <?= $dataSource['is_active'] ? 'btn--warning' : 'btn--success' ?>">
                <?= $dataSource['is_active'] ? 'Deactivate' : 'Activate' ?>
            </button>
        </form>
        <?php if (empty($usingPolicies)): ?>
            <form method="post" action="<?= Url::to(['delete', 'id' => $dataSource['id']]) ?>" style="display: inline;"
                  onsubmit="return confirm('Are you sure you want to delete this data source?');">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>
                <button type="submit" class="btn btn--danger">Delete</button>
            </form>
        <?php else: ?>
            <button type="button" class="btn btn--secondary" disabled
                    style="opacity: 0.5; cursor: not-allowed;"
                    title="Cannot delete: used by <?= count($usingPolicies) ?> collection <?= count($usingPolicies) === 1 ? 'policy' : 'policies' ?>">
                Delete
            </button>
        <?php endif; ?>
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
            <div class="detail-label">ID</div>
            <div class="detail-value"><code><?= Html::encode($dataSource['id']) ?></code></div>

            <div class="detail-label">Name</div>
            <div class="detail-value"><?= Html::encode($dataSource['name']) ?></div>

            <div class="detail-label">Type</div>
            <div class="detail-value">
                <span class="badge <?= $typeClass[$dataSource['source_type']] ?? '' ?>">
                    <?= $typeLabels[$dataSource['source_type']] ?? $dataSource['source_type'] ?>
                </span>
            </div>

            <div class="detail-label">Base URL</div>
            <div class="detail-value">
                <?php if (!empty($dataSource['base_url'])): ?>
                    <a href="<?= Html::encode($dataSource['base_url']) ?>" target="_blank" rel="noopener">
                        <?= Html::encode($dataSource['base_url']) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">-</span>
                <?php endif; ?>
            </div>

            <div class="detail-label">Status</div>
            <div class="detail-value">
                <?php if ($dataSource['is_active']): ?>
                    <span class="badge badge--active">Active</span>
                <?php else: ?>
                    <span class="badge badge--inactive">Inactive</span>
                <?php endif; ?>
            </div>

            <div class="detail-label">Created</div>
            <div class="detail-value"><?= Html::encode($dataSource['created_at']) ?></div>

            <div class="detail-label">Updated</div>
            <div class="detail-value"><?= Html::encode($dataSource['updated_at']) ?></div>

            <?php if (!empty($dataSource['notes'])): ?>
                <div class="detail-label">Notes</div>
                <div class="detail-value"><?= nl2br(Html::encode($dataSource['notes'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Used by Collection Policies</h2>
    </div>
    <div class="card__body">
        <?php if (empty($usingPolicies)): ?>
            <p class="text-muted">This data source is not configured in any collection policies.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Policy</th>
                            <th>Categories</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usingPolicies as $policy): ?>
                            <tr>
                                <td>
                                    <a href="<?= Url::to(['/collection-policy/view', 'slug' => $policy['slug']]) ?>">
                                        <?= Html::encode($policy['name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php foreach ($policy['categories'] as $category): ?>
                                        <span class="badge badge--info"><?= Html::encode($category) ?></span>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
