<?php

declare(strict_types=1);

use app\dto\industryconfig\IndustryConfigResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryConfigResponse[] $configs
 * @var int $total
 * @var array{total: int, active: int, inactive: int} $counts
 * @var string|null $currentStatus
 * @var string|null $currentSearch
 * @var string $currentOrder
 * @var string $currentDir
 */

$this->title = 'Industry Configurations';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
        + New Configuration
    </a>
</div>

<div class="filter-bar">
    <div class="filter-bar__tabs">
        <a href="<?= Url::to(['index']) ?>"
           class="filter-tab <?= $currentStatus === null ? 'filter-tab--active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'active']) ?>"
           class="filter-tab <?= $currentStatus === 'active' ? 'filter-tab--active' : '' ?>">
            Active (<?= $counts['active'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'inactive']) ?>"
           class="filter-tab <?= $currentStatus === 'inactive' ? 'filter-tab--active' : '' ?>">
            Inactive (<?= $counts['inactive'] ?>)
        </a>
    </div>

    <div class="filter-bar__search">
        <form method="get" action="<?= Url::to(['index']) ?>">
            <?php if ($currentStatus !== null): ?>
                <input type="hidden" name="status" value="<?= Html::encode($currentStatus) ?>">
            <?php endif; ?>
            <input type="text"
                   name="search"
                   class="search-input"
                   placeholder="Search by name or ID..."
                   value="<?= Html::encode($currentSearch ?? '') ?>">
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($configs)): ?>
        <div class="empty-state">
            <div class="empty-state__icon">📋</div>
            <h3 class="empty-state__title">No configurations found</h3>
            <p class="empty-state__text">
                <?php if ($currentSearch !== null): ?>
                    No configurations match your search criteria.
                <?php elseif ($currentStatus !== null): ?>
                    No <?= Html::encode($currentStatus) ?> configurations exist.
                <?php else: ?>
                    Get started by creating your first industry configuration.
                <?php endif; ?>
            </p>
            <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
                + Create Configuration
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Industry ID',
                                'column' => 'industry_id',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                            ]) ?>
                        </th>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Name',
                                'column' => 'name',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                            ]) ?>
                        </th>
                        <th>Status</th>
                        <th>Valid</th>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Updated',
                                'column' => 'updated_at',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                            ]) ?>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($configs as $config): ?>
                        <tr>
                            <td>
                                <a href="<?= Url::to(['view', 'industry_id' => $config->industryId]) ?>">
                                    <?= Html::encode($config->industryId) ?>
                                </a>
                            </td>
                            <td><?= Html::encode($config->name) ?></td>
                            <td>
                                <span class="badge <?= $config->isActive ? 'badge--active' : 'badge--inactive' ?>">
                                    <?= $config->isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $config->isJsonValid ? 'badge--valid' : 'badge--invalid' ?>">
                                    <?= $config->isJsonValid ? 'Valid' : 'Invalid' ?>
                                </span>
                            </td>
                            <td><?= Html::encode($config->updatedAt->format('Y-m-d H:i')) ?></td>
                            <td>
                                <div class="table__actions">
                                    <a href="<?= Url::to(['view', 'industry_id' => $config->industryId]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        View
                                    </a>
                                    <a href="<?= Url::to(['update', 'industry_id' => $config->industryId]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        Edit
                                    </a>
                                    <form method="post"
                                          action="<?= Url::to(['toggle', 'industry_id' => $config->industryId]) ?>"
                                          style="display: inline;">
                                        <?= Html::hiddenInput(
                                            Yii::$app->request->csrfParam,
                                            Yii::$app->request->csrfToken
                                        ) ?>
                                        <input type="hidden" name="return_url" value="<?= Url::to(['index']) ?>">
                                        <button type="submit"
                                                class="btn btn--sm <?= $config->isActive ? 'btn--danger' : 'btn--success' ?>">
                                            <?= $config->isActive ? 'Disable' : 'Enable' ?>
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
