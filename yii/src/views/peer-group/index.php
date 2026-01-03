<?php

declare(strict_types=1);

use app\dto\peergroup\PeerGroupResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var PeerGroupResponse[] $groups
 * @var array{total: int, active: int, inactive: int} $counts
 * @var string[] $sectors
 * @var string|null $currentSector
 * @var string|null $currentStatus
 * @var string|null $currentSearch
 * @var string $currentOrder
 * @var string $currentDir
 */

$this->title = 'Peer Groups';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
        + New Peer Group
    </a>
</div>

<div class="filter-bar">
    <div class="filter-bar__tabs">
        <a href="<?= Url::to(['index', 'sector' => $currentSector]) ?>"
           class="filter-tab <?= $currentStatus === null ? 'filter-tab--active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'active', 'sector' => $currentSector]) ?>"
           class="filter-tab <?= $currentStatus === 'active' ? 'filter-tab--active' : '' ?>">
            Active (<?= $counts['active'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'inactive', 'sector' => $currentSector]) ?>"
           class="filter-tab <?= $currentStatus === 'inactive' ? 'filter-tab--active' : '' ?>">
            Inactive (<?= $counts['inactive'] ?>)
        </a>
    </div>

    <div class="filter-bar__controls">
        <?php if (!empty($sectors)): ?>
            <select class="search-input search-input--compact" onchange="filterBySector(this.value)">
                <option value="">All Sectors</option>
                <?php foreach ($sectors as $sector): ?>
                    <option value="<?= Html::encode($sector) ?>" <?= $currentSector === $sector ? 'selected' : '' ?>>
                        <?= Html::encode($sector) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <form method="get" action="<?= Url::to(['index']) ?>">
            <?php if ($currentStatus !== null): ?>
                <input type="hidden" name="status" value="<?= Html::encode($currentStatus) ?>">
            <?php endif; ?>
            <?php if ($currentSector !== null): ?>
                <input type="hidden" name="sector" value="<?= Html::encode($currentSector) ?>">
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
    <?php if (empty($groups)): ?>
        <div class="empty-state">
            <h3 class="empty-state__title">No peer groups found</h3>
            <p class="empty-state__text">
                <?php if ($currentSearch !== null): ?>
                    No peer groups match your search criteria.
                <?php elseif ($currentStatus !== null): ?>
                    No <?= Html::encode($currentStatus) ?> peer groups exist.
                <?php else: ?>
                    Get started by creating your first peer group.
                <?php endif; ?>
            </p>
            <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
                + Create Peer Group
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
                                'currentSector' => $currentSector,
                            ]) ?>
                        </th>
                        <th>
                            <?= $this->render('_sort_header', [
                                'label' => 'Sector',
                                'column' => 'sector',
                                'currentOrder' => $currentOrder,
                                'currentDir' => $currentDir,
                                'currentStatus' => $currentStatus,
                                'currentSearch' => $currentSearch,
                                'currentSector' => $currentSector,
                            ]) ?>
                        </th>
                        <th>Members</th>
                        <th>Focals</th>
                        <th>Policy</th>
                        <th>Status</th>
                        <th>Last Run</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td>
                                <a href="<?= Url::to(['view', 'slug' => $group->slug]) ?>">
                                    <?= Html::encode($group->name) ?>
                                </a>
                            </td>
                            <td><?= Html::encode($group->sector) ?></td>
                            <td class="table__cell--number"><?= $group->memberCount ?></td>
                            <td>
                                <?php if ($group->focalCount > 0): ?>
                                    <?php foreach ($group->focalTickers as $ticker): ?>
                                        <span class="badge badge--info"><?= Html::encode($ticker) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($group->policyName !== null): ?>
                                    <?= Html::encode($group->policyName) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $group->isActive ? 'badge--active' : 'badge--inactive' ?>">
                                    <?= $group->isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($group->lastRunStatus !== null): ?>
                                    <span class="badge badge--<?= $group->lastRunStatus === 'complete' ? 'valid' : ($group->lastRunStatus === 'failed' ? 'invalid' : 'info') ?>">
                                        <?= Html::encode(ucfirst($group->lastRunStatus)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table__actions">
                                    <a href="<?= Url::to(['view', 'slug' => $group->slug]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        View
                                    </a>
                                    <a href="<?= Url::to(['update', 'slug' => $group->slug]) ?>"
                                       class="btn btn--sm btn--secondary">
                                        Edit
                                    </a>
                                    <form method="post"
                                          action="<?= Url::to(['toggle', 'slug' => $group->slug]) ?>">
                                        <?= Html::hiddenInput(
                                            Yii::$app->request->csrfParam,
                                            Yii::$app->request->csrfToken
                                        ) ?>
                                        <input type="hidden" name="return_url" value="<?= Url::to(['index']) ?>">
                                        <button type="submit"
                                                class="btn btn--sm <?= $group->isActive ? 'btn--danger' : 'btn--success' ?>">
                                            <?= $group->isActive ? 'Disable' : 'Enable' ?>
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
function filterBySector(sector) {
    const params = new URLSearchParams(window.location.search);
    if (sector) {
        params.set('sector', sector);
    } else {
        params.delete('sector');
    }
    window.location.href = '<?= Url::to(['index']) ?>' + (params.toString() ? '?' + params.toString() : '');
}
</script>
