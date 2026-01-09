<?php

declare(strict_types=1);

use app\dto\industry\IndustryResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryResponse[] $industries
 * @var array{total: int, active: int, inactive: int} $counts
 * @var array<array{id: int, slug: string, name: string}>  $sectors
 * @var int|null $currentSectorId
 * @var string|null $currentStatus
 * @var string|null $currentSearch
 * @var string $currentOrder
 * @var string $currentDir
 */

$this->title = 'Industries';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
        + New Industry
    </a>
</div>

<div class="filter-bar">
    <div class="filter-bar__tabs">
        <a href="<?= Url::to(['index', 'sector' => $currentSectorId]) ?>"
           class="filter-tab <?= $currentStatus === null ? 'filter-tab--active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'active', 'sector' => $currentSectorId]) ?>"
           class="filter-tab <?= $currentStatus === 'active' ? 'filter-tab--active' : '' ?>">
            Active (<?= $counts['active'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'inactive', 'sector' => $currentSectorId]) ?>"
           class="filter-tab <?= $currentStatus === 'inactive' ? 'filter-tab--active' : '' ?>">
            Inactive (<?= $counts['inactive'] ?>)
        </a>
    </div>

    <div class="filter-bar__controls">
        <?php if (!empty($sectors)): ?>
            <select class="search-input search-input--compact" onchange="filterBySector(this.value)">
                <option value="">All Sectors</option>
                <?php foreach ($sectors as $sector): ?>
                    <option value="<?= (int) $sector['id'] ?>" <?= $currentSectorId === (int) $sector['id'] ? 'selected' : '' ?>>
                        <?= Html::encode($sector['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <form method="get" action="<?= Url::to(['index']) ?>">
            <?php if ($currentStatus !== null): ?>
                <input type="hidden" name="status" value="<?= Html::encode($currentStatus) ?>">
            <?php endif; ?>
            <?php if ($currentSectorId !== null): ?>
                <input type="hidden" name="sector" value="<?= $currentSectorId ?>">
            <?php endif; ?>
            <input type="text"
                   name="search"
                   class="search-input"
                   placeholder="Search by name or slug..."
                   value="<?= Html::encode($currentSearch ?? '') ?>">
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($industries)): ?>
        <div class="empty-state">
            <h3 class="empty-state__title">No industries found</h3>
            <p class="empty-state__text">
                <?php if ($currentSearch !== null): ?>
                    No industries match your search criteria.
                <?php elseif ($currentStatus !== null): ?>
                    No <?= Html::encode($currentStatus) ?> industries exist.
                <?php else: ?>
                    Get started by creating your first industry.
                <?php endif; ?>
            </p>
            <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
                + Create Industry
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Name',
                                'column' => 'name',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                                'currentSectorId' => $currentSectorId,
                            ]) ?>
                        </th>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Sector',
                                'column' => 'sector_name',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                                'currentSectorId' => $currentSectorId,
                            ]) ?>
                        </th>
                        <th>Companies</th>
                        <th>Policy</th>
                        <th>Status</th>
                        <th>Last Run</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($industries as $industry): ?>
                        <tr>
                            <td>
                                <a href="<?= Url::to(['view', 'slug' => $industry->slug]) ?>">
                                    <?= Html::encode($industry->name) ?>
                                </a>
                            </td>
                            <td><?= Html::encode($industry->sectorName) ?></td>
                            <td class="table__cell--number"><?= $industry->companyCount ?></td>
                            <td>
                                <?php if ($industry->policyName !== null): ?>
                                    <?= Html::encode($industry->policyName) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $industry->isActive ? 'badge--active' : 'badge--inactive' ?>">
                                    <?= $industry->isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($industry->lastRunStatus !== null): ?>
                                    <span class="badge badge--<?= $industry->lastRunStatus === 'complete' ? 'valid' : ($industry->lastRunStatus === 'failed' ? 'invalid' : 'info') ?>">
                                        <?= Html::encode(ucfirst($industry->lastRunStatus)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table__actions">
                                    <a href="<?= Url::to(['view', 'slug' => $industry->slug]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        View
                                    </a>
                                    <a href="<?= Url::to(['update', 'slug' => $industry->slug]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        Edit
                                    </a>
                                    <form method="post"
                                          action="<?= Url::to(['toggle', 'slug' => $industry->slug]) ?>">
                                        <?= Html::hiddenInput(
                                            Yii::$app->request->csrfParam,
                                            Yii::$app->request->csrfToken
                                        ) ?>
                                        <input type="hidden" name="return_url" value="<?= Url::to(['index']) ?>">
                                        <button type="submit"
                                                class="btn btn--sm <?= $industry->isActive ? 'btn--danger' : 'btn--success' ?>">
                                            <?= $industry->isActive ? 'Disable' : 'Enable' ?>
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

<script>
function filterBySector(sectorId) {
    const params = new URLSearchParams(window.location.search);
    if (sectorId) {
        params.set('sector', sectorId);
    } else {
        params.delete('sector');
    }
    window.location.href = '<?= Url::to(['index']) ?>' + (params.toString() ? '?' + params.toString() : '');
}
</script>
