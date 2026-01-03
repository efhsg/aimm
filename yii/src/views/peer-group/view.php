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
              >
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

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Members (<?= count($members) ?>)</h2>
        <div class="toolbar">
            <?php
            $focalCount = array_reduce($members, static fn ($carry, $m) => $carry + ($m['is_focal'] ? 1 : 0), 0);
if ($focalCount > 0): ?>
                <form method="post"
                      action="<?= Url::to(['clear-focals', 'slug' => $group->slug]) ?>">
                    <?= Html::hiddenInput(
                        Yii::$app->request->csrfParam,
                        Yii::$app->request->csrfToken
                    ) ?>
                    <button type="submit" class="btn btn--secondary btn--sm">
                        Clear All Focals (<?= $focalCount ?>)
                    </button>
                </form>
            <?php endif; ?>
            <button type="button" class="btn btn--primary btn--sm" id="add-members-btn">
                + Add Companies
            </button>
        </div>
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
                            <th>Focals</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td class="table__cell--mono">
                                    <strong><?= Html::encode($member['ticker']) ?></strong>
                                </td>
                                <td><?= Html::encode($member['name']) ?></td>
                                <td>
                                    <?php if ($member['is_focal']): ?>
                                        <div class="table__actions">
                                            <span class="badge badge--info">Focal</span>
                                            <form method="post"
                                                  action="<?= Url::to(['remove-focal', 'slug' => $group->slug]) ?>">
                                                <?= Html::hiddenInput(
                                                    Yii::$app->request->csrfParam,
                                                    Yii::$app->request->csrfToken
                                                ) ?>
                                                <input type="hidden" name="company_id" value="<?= $member['company_id'] ?>">
                                                <button type="submit" class="btn btn--sm btn--secondary">
                                                    Remove Focal
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="table__actions">
                                            <form method="post"
                                                  action="<?= Url::to(['add-focal', 'slug' => $group->slug]) ?>">
                                                <?= Html::hiddenInput(
                                                    Yii::$app->request->csrfParam,
                                                    Yii::$app->request->csrfToken
                                                ) ?>
                                                <input type="hidden" name="company_id" value="<?= $member['company_id'] ?>">
                                                <button type="submit" class="btn btn--sm btn--secondary">
                                                    Add Focal
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post"
                                          action="<?= Url::to(['remove-member', 'slug' => $group->slug]) ?>"
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

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Collection</h2>
        <?php if ($group->isActive && count($members) > 0): ?>
            <form method="post"
                  action="<?= Url::to(['collect', 'slug' => $group->slug]) ?>"
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
                                <td class="table__cell--number">
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
                                <td class="table__cell--number">
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
<div id="add-members-modal" class="modal modal--hidden">
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

<script>
document.getElementById('add-members-btn').addEventListener('click', function() {
    document.getElementById('add-members-modal').classList.remove('modal--hidden');
});

function closeModal() {
    document.getElementById('add-members-modal').classList.add('modal--hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
