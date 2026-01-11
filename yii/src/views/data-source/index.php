<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed>[] $dataSources
 * @var array{total: int, active: int, inactive: int} $counts
 * @var string|null $currentStatus
 * @var string|null $currentType
 * @var string $search
 */

$this->title = 'Data Sources';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
            + Create Data Source
        </a>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-bar__tabs">
        <a href="<?= Url::to(['index', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === null ? ' filter-tab--active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'active', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === 'active' ? ' filter-tab--active' : '' ?>">
            Active (<?= $counts['active'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'inactive', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === 'inactive' ? ' filter-tab--active' : '' ?>">
            Inactive (<?= $counts['inactive'] ?>)
        </a>
    </div>

    <div class="filter-bar__controls">
        <select class="form-select" onchange="location.href=this.value">
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'search' => $search]) ?>"
                <?= $currentType === null ? 'selected' : '' ?>>All Types</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'api', 'search' => $search]) ?>"
                <?= $currentType === 'api' ? 'selected' : '' ?>>API</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'web_scrape', 'search' => $search]) ?>"
                <?= $currentType === 'web_scrape' ? 'selected' : '' ?>>Web Scrape</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'derived', 'search' => $search]) ?>"
                <?= $currentType === 'derived' ? 'selected' : '' ?>>Derived</option>
        </select>

        <form method="get" action="<?= Url::to(['index']) ?>">
            <?php if ($currentStatus !== null): ?>
                <input type="hidden" name="status" value="<?= Html::encode($currentStatus) ?>">
            <?php endif; ?>
            <?php if ($currentType !== null): ?>
                <input type="hidden" name="type" value="<?= Html::encode($currentType) ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= Html::encode($search) ?>"
                   placeholder="Search by ID or name..." class="search-input">
        </form>
    </div>
</div>

<div class="card">
    <div class="card__body">
        <?php if (empty($dataSources)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No data sources found</h3>
                <p class="empty-state__text">
                    <?php if ($search !== '' || $currentStatus !== null || $currentType !== null): ?>
                        Try adjusting your filters or search term.
                    <?php else: ?>
                        Create a data source to track where your financial data comes from.
                    <?php endif; ?>
                </p>
                <?php if ($search === '' && $currentStatus === null && $currentType === null): ?>
                    <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
                        + Create Data Source
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Base URL</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataSources as $ds): ?>
                            <tr>
                                <td class="table__cell--mono">
                                    <a href="<?= Url::to(['view', 'id' => $ds['id']]) ?>">
                                        <code><?= Html::encode($ds['id']) ?></code>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= Html::encode($ds['name']) ?></strong>
                                    <?php if (!empty($ds['notes'])): ?>
                                        <br>
                                        <span class="text-muted text-sm">
                                            <?= Html::encode(mb_substr($ds['notes'], 0, 50)) ?>
                                            <?= mb_strlen($ds['notes']) > 50 ? '...' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <?php
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
                                    <span class="badge <?= $typeClass[$ds['source_type']] ?? '' ?>">
                                        <?= $typeLabels[$ds['source_type']] ?? $ds['source_type'] ?>
                                    </span>
                                </td>
                                <td class="table__cell--mono">
                                    <?php if (!empty($ds['base_url'])): ?>
                                        <a href="<?= Html::encode($ds['base_url']) ?>" target="_blank" rel="noopener">
                                            <?= Html::encode(parse_url($ds['base_url'], PHP_URL_HOST) ?: $ds['base_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ds['is_active']): ?>
                                        <span class="badge badge--active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge--inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table__actions">
                                        <a href="<?= Url::to(['view', 'id' => $ds['id']]) ?>"
                                           class="btn btn--sm btn--secondary">View</a>
                                        <a href="<?= Url::to(['update', 'id' => $ds['id']]) ?>"
                                           class="btn btn--sm btn--secondary">Edit</a>
                                        <form method="post" action="<?= Url::to(['toggle', 'id' => $ds['id']]) ?>"
                                              style="display: inline;">
                                            <?= Html::hiddenInput(
                                                Yii::$app->request->csrfParam,
                                                Yii::$app->request->getCsrfToken()
                                            ) ?>
                                            <button type="submit" class="btn btn--sm <?= $ds['is_active'] ? 'btn--warning' : 'btn--success' ?>">
                                                <?= $ds['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
