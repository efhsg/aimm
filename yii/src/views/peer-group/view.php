<?php

declare(strict_types=1);

use app\dto\peergroup\PeerGroupResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var PeerGroupResponse $group
 * @var array{company_id: int, is_focal: bool, display_order: int, ticker: string, name: string}[] $members
 * @var array{id: int, status: string, started_at: string, completed_at: ?string, companies_total: int, companies_success: int, companies_failed: int, gate_passed: ?int, error_count: int, warning_count: int}[] $runs
 */

$this->title = $group->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['update', 'slug' => $group->slug]) ?>" class="btn btn--primary">
            Edit
        </a>
        <form method="post"
              action="<?= Url::to(['toggle', 'slug' => $group->slug]) ?>"
              style="display: inline;">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>
            <input type="hidden" name="return_url" value="<?= Url::to(['view', 'slug' => $group->slug]) ?>">
            <button type="submit"
                    class="btn <?= $group->isActive ? 'btn--danger' : 'btn--success' ?>">
                <?= $group->isActive ? 'Deactivate' : 'Activate' ?>
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
        <span class="badge <?= $group->isActive ? 'badge--active' : 'badge--inactive' ?>">
            <?= $group->isActive ? 'Active' : 'Inactive' ?>
        </span>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Slug</div>
            <div class="detail-value"><?= Html::encode($group->slug) ?></div>

            <div class="detail-label">Sector</div>
            <div class="detail-value"><?= Html::encode($group->sector) ?></div>

            <div class="detail-label">Policy</div>
            <div class="detail-value">
                <?= $group->policyName !== null ? Html::encode($group->policyName) : '<span class="text-muted">Not assigned</span>' ?>
            </div>

            <div class="detail-label">Description</div>
            <div class="detail-value">
                <?= $group->description !== null ? Html::encode($group->description) : '<span class="text-muted">No description</span>' ?>
            </div>

            <div class="detail-label">Created</div>
            <div class="detail-value">
                <?= Html::encode($group->createdAt->format('Y-m-d H:i:s')) ?>
                <?php if ($group->createdBy !== null): ?>
                    by <?= Html::encode($group->createdBy) ?>
                <?php endif; ?>
            </div>

            <div class="detail-label">Updated</div>
            <div class="detail-value">
                <?= Html::encode($group->updatedAt->format('Y-m-d H:i:s')) ?>
                <?php if ($group->updatedBy !== null): ?>
                    by <?= Html::encode($group->updatedBy) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: var(--space-6);">
    <div class="card__header">
        <h2 class="card__title">Members (<?= count($members) ?>)</h2>
        <button type="button" class="btn btn--primary btn--sm" id="add-members-btn">
            + Add Companies
        </button>
    </div>
    <div class="card__body">
        <?php if (empty($members)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No members yet</h3>
                <p class="empty-state__text">Add companies to this peer group to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table" id="members-table">
                    <thead>
                        <tr>
                            <th>Ticker</th>
                            <th>Company Name</th>
                            <th>Focal</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td>
                                    <strong><?= Html::encode($member['ticker']) ?></strong>
                                </td>
                                <td><?= Html::encode($member['name']) ?></td>
                                <td>
                                    <?php if ($member['is_focal']): ?>
                                        <span class="badge badge--info">Focal</span>
                                    <?php else: ?>
                                        <form method="post"
                                              action="<?= Url::to(['set-focal', 'slug' => $group->slug]) ?>"
                                              style="display: inline;">
                                            <?= Html::hiddenInput(
                                                Yii::$app->request->csrfParam,
                                                Yii::$app->request->csrfToken
                                            ) ?>
                                            <input type="hidden" name="company_id" value="<?= $member['company_id'] ?>">
                                            <button type="submit" class="btn btn--sm btn--secondary">
                                                Set Focal
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post"
                                          action="<?= Url::to(['remove-member', 'slug' => $group->slug]) ?>"
                                          style="display: inline;"
                                          onsubmit="return confirm('Remove <?= Html::encode($member['ticker']) ?> from this group?');">
                                        <?= Html::hiddenInput(
                                            Yii::$app->request->csrfParam,
                                            Yii::$app->request->csrfToken
                                        ) ?>
                                        <input type="hidden" name="company_id" value="<?= $member['company_id'] ?>">
                                        <button type="submit" class="btn btn--sm btn--danger">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: var(--space-6);">
    <div class="card__header">
        <h2 class="card__title">Collection</h2>
        <?php if ($group->isActive && count($members) > 0): ?>
            <form method="post"
                  action="<?= Url::to(['collect', 'slug' => $group->slug]) ?>"
                  style="display: inline;"
                  onsubmit="return confirm('Start data collection for this peer group?');">
                <?= Html::hiddenInput(
                    Yii::$app->request->csrfParam,
                    Yii::$app->request->csrfToken
                ) ?>
                <button type="submit" class="btn btn--primary btn--sm">
                    Collect Data
                </button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card__body">
        <?php if (!$group->isActive): ?>
            <div class="alert alert--warning">
                Activate this peer group to enable data collection.
            </div>
        <?php elseif (count($members) === 0): ?>
            <div class="alert alert--warning">
                Add members to this peer group before collecting data.
            </div>
        <?php elseif (empty($runs)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No collection runs yet</h3>
                <p class="empty-state__text">Click "Collect Data" to start collecting financial data for this peer group.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Run ID</th>
                            <th>Started</th>
                            <th>Status</th>
                            <th>Companies</th>
                            <th>Gate</th>
                            <th>Issues</th>
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
                                    <a href="<?= Url::to(['collection-run/view', 'id' => $run['id']]) ?>">
                                        #<?= $run['id'] ?>
                                    </a>
                                </td>
                                <td><?= Html::encode($run['started_at']) ?></td>
                                <td>
                                    <span class="badge <?= $statusClass ?>">
                                        <?= Html::encode(ucfirst($run['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $run['companies_success'] ?>/<?= $run['companies_total'] ?>
                                </td>
                                <td>
                                    <?php if ($run['status'] === 'complete'): ?>
                                        <?= (bool)($run['gate_passed'] ?? false) ? 'Passed' : 'Failed' ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Members Modal -->
<div id="add-members-modal" class="modal" style="display: none;">
    <div class="modal__backdrop" onclick="closeModal()"></div>
    <div class="modal__content">
        <div class="modal__header">
            <h3 class="modal__title">Add Companies</h3>
            <button type="button" class="modal__close" onclick="closeModal()">&times;</button>
        </div>
        <form method="post" action="<?= Url::to(['add-members', 'slug' => $group->slug]) ?>">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>
            <div class="modal__body">
                <div class="form-group">
                    <label for="tickers" class="form-label">Ticker Symbols</label>
                    <textarea id="tickers"
                              name="tickers"
                              class="form-textarea"
                              rows="6"
                              placeholder="Enter ticker symbols (one per line or comma-separated)&#10;&#10;Examples:&#10;AAPL&#10;MSFT, GOOGL, META"></textarea>
                    <p class="form-help">
                        Companies will be created automatically if they don't exist.
                    </p>
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn--primary">Add Companies</button>
            </div>
        </form>
    </div>
</div>

<style>
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-4);
}
.alert--warning {
    background: var(--color-warning-50, #fffbeb);
    border: 1px solid var(--color-warning-200, #fde68a);
    color: var(--color-warning-800, #92400e);
}
.text-danger {
    color: var(--color-error-600, #dc2626);
}
.text-warning {
    color: var(--color-warning-600, #d97706);
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: var(--z-modal, 400);
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal__backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}
.modal__content {
    position: relative;
    background: var(--bg-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: 100%;
    max-width: 500px;
    margin: var(--space-4);
}
.modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-4) var(--space-6);
    border-bottom: 1px solid var(--border-default);
}
.modal__title {
    font-size: var(--text-lg);
    font-weight: 600;
    margin: 0;
}
.modal__close {
    background: none;
    border: none;
    font-size: var(--text-2xl);
    cursor: pointer;
    color: var(--text-secondary);
    line-height: 1;
}
.modal__close:hover {
    color: var(--text-primary);
}
.modal__body {
    padding: var(--space-6);
}
.modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    padding: var(--space-4) var(--space-6);
    border-top: 1px solid var(--border-default);
}
</style>

<script>
document.getElementById('add-members-btn').addEventListener('click', function() {
    document.getElementById('add-members-modal').style.display = 'flex';
});

function closeModal() {
    document.getElementById('add-members-modal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
