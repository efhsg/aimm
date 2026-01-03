<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

/**
 * @var yii\web\View $this
 * @var array{id: int, industry_id: string, datapack_id: string, status: string, started_at: string, completed_at: ?string, companies_total: int, companies_success: int, companies_failed: int, gate_passed: ?int, error_count: int, warning_count: int, duration_seconds: int}[] $runs
 * @var string|null $currentStatus
 * @var string|null $currentSearch
 * @var yii\data\Pagination $pagination
 * @var int $totalCount
 * @var array<string, string> $statusOptions
 */

$this->title = 'Collection Runs';

?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
</div>

<form method="get" class="filter-bar">
    <div class="filter-bar__controls">
        <select name="status" class="search-input search-input--compact">
            <option value="" <?= ($currentStatus ?? '') === '' ? 'selected' : '' ?>>All Statuses</option>
            <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= Html::encode($value) ?>" <?= ($currentStatus ?? '') === $value ? 'selected' : '' ?>>
                    <?= Html::encode($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input
            type="text"
            name="search"
            class="search-input"
            placeholder="Search by peer group or datapack ID..."
            value="<?= Html::encode($currentSearch ?? '') ?>"
        >
        <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
    </div>
</form>

<div class="card">
    <div class="card__body">
        <?php if (empty($runs)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No collection runs found</h3>
                <p class="empty-state__text">Try adjusting your filters or search criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Run ID</th>
                            <th>Peer Group</th>
                            <th>Datapack ID</th>
                            <th>Status</th>
                            <th>Gate</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Duration</th>
                            <th>Companies</th>
                            <th>Issues</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($runs as $run): ?>
                            <?php
                            $statusClass = match ($run['status']) {
                                'complete' => ((bool)($run['gate_passed'] ?? false)) ? 'badge--success' : 'badge--warning',
                                'running' => 'badge--info',
                                'failed' => 'badge--danger',
                                default => 'badge--inactive',
                            };
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= Url::to(['view', 'id' => $run['id']]) ?>">
                                        #<?= $run['id'] ?>
                                    </a>
                                </td>
                                <td><?= Html::encode($run['industry_id']) ?></td>
                                <td class="table__cell--mono"><?= Html::encode($run['datapack_id']) ?></td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= Html::encode(ucfirst($run['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($run['status'] === 'complete'): ?>
                                        <?php if ((bool)($run['gate_passed'] ?? false)): ?>
                                            <span class="text-success">Passed</span>
                                        <?php else: ?>
                                            <span class="text-danger">Failed</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= Html::encode($run['started_at']) ?></td>
                                <td>
                                    <?= $run['completed_at'] !== null ? Html::encode($run['completed_at']) : '<span class="text-muted">In progress...</span>' ?>
                                </td>
                                <td class="table__cell--number">
                                    <?= $run['duration_seconds'] > 0 ? $run['duration_seconds'] . 's' : '-' ?>
                                </td>
                                <td class="table__cell--number">
                                    <?= $run['companies_success'] ?>/<?= $run['companies_total'] ?>
                                </td>
                                <td>
                                    <?php if ($run['error_count'] > 0): ?>
                                        <span class="text-danger"><?= $run['error_count'] ?> errors</span>
                                    <?php endif; ?>
                                    <?php if ($run['warning_count'] > 0): ?>
                                        <span class="text-warning"><?= $run['warning_count'] ?> warnings</span>
                                    <?php endif; ?>
                                    <?php if ($run['error_count'] === 0 && $run['warning_count'] === 0): ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table__actions">
                                        <a href="<?= Url::to(['view', 'id' => $run['id']]) ?>"
                                           class="btn btn--sm btn--secondary">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination-bar">
                <p class="text-muted text-sm">
                    <?php
                    $start = $pagination->offset + 1;
$end = $pagination->offset + count($runs);
?>
                    Showing <?= $start ?>-<?= $end ?> of <?= $totalCount ?> runs
                </p>
                <?= LinkPager::widget([
'pagination' => $pagination,
'options' => ['class' => 'pagination'],
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
