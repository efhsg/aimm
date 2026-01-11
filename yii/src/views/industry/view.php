<?php

declare(strict_types=1);

use app\dto\industry\IndustryResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryResponse $industry
 * @var array{id: int, ticker: string, name: ?string}[] $companies
 * @var array{id: int, status: string, started_at: string, completed_at: ?string, companies_total: int, companies_success: int, companies_failed: int, gate_passed: ?int, error_count: int, warning_count: int}[] $runs
 */

$this->title = $industry->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['update', 'slug' => $industry->slug]) ?>" class="btn btn--primary">
            Edit
        </a>
        <form method="post"
              action="<?= Url::to(['toggle', 'slug' => $industry->slug]) ?>"
              >
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>
            <input type="hidden" name="return_url" value="<?= Url::to(['view', 'slug' => $industry->slug]) ?>">
            <button type="submit"
                    class="btn <?= $industry->isActive ? 'btn--danger' : 'btn--success' ?>">
                <?= $industry->isActive ? 'Deactivate' : 'Activate' ?>
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
        <span class="badge <?= $industry->isActive ? 'badge--active' : 'badge--inactive' ?>">
            <?= $industry->isActive ? 'Active' : 'Inactive' ?>
        </span>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Slug</div>
            <div class="detail-value"><?= Html::encode($industry->slug) ?></div>

            <div class="detail-label">Sector</div>
            <div class="detail-value"><?= Html::encode($industry->sectorName) ?></div>

            <div class="detail-label">Policy</div>
            <div class="detail-value">
                <?= $industry->policyName !== null ? Html::encode($industry->policyName) : '<span class="text-muted">Not assigned</span>' ?>
            </div>

            <div class="detail-label">Description</div>
            <div class="detail-value">
                <?= $industry->description !== null ? Html::encode($industry->description) : '<span class="text-muted">No description</span>' ?>
            </div>

            <div class="detail-label">Created</div>
            <div class="detail-value">
                <?= Html::encode($industry->createdAt->format('Y-m-d H:i:s')) ?>
                <?php if ($industry->createdBy !== null): ?>
                    by <?= Html::encode($industry->createdBy) ?>
                <?php endif; ?>
            </div>

            <div class="detail-label">Updated</div>
            <div class="detail-value">
                <?= Html::encode($industry->updatedAt->format('Y-m-d H:i:s')) ?>
                <?php if ($industry->updatedBy !== null): ?>
                    by <?= Html::encode($industry->updatedBy) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Companies (<?= count($companies) ?>)</h2>
        <div class="toolbar">
            <button type="button" class="btn btn--primary btn--sm" id="add-members-btn">
                + Add Companies
            </button>
        </div>
    </div>
    <div class="card__body">
        <?php if (empty($companies)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No companies yet</h3>
                <p class="empty-state__text">Add companies to this industry to get started.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table" id="companies-table">
                    <thead>
                        <tr>
                            <th>Ticker</th>
                            <th>Company Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td class="table__cell--mono">
                                    <strong><?= Html::encode($company['ticker']) ?></strong>
                                </td>
                                <td><?= Html::encode($company['name'] ?? $company['ticker']) ?></td>
                                <td>
                                    <form method="post"
                                          action="<?= Url::to(['remove-member', 'slug' => $industry->slug]) ?>"
                                          onsubmit="return confirm('Remove <?= Html::encode($company['ticker']) ?> from this industry?');">
                                        <?= Html::hiddenInput(
                                            Yii::$app->request->csrfParam,
                                            Yii::$app->request->csrfToken
                                        ) ?>
                                        <input type="hidden" name="company_id" value="<?= $company['id'] ?>">
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
        <h2 class="card__title">Collection & Analysis</h2>
        <div class="toolbar">
            <?php if ($industry->isActive && count($companies) >= 2): ?>
                <form method="post"
                      action="<?= Url::to(['analyze', 'slug' => $industry->slug]) ?>"
                      onsubmit="return confirm('Run analysis and ranking for all companies?');">
                    <?= Html::hiddenInput(
                        Yii::$app->request->csrfParam,
                        Yii::$app->request->csrfToken
                    ) ?>
                    <button type="submit" class="btn btn--success btn--sm">
                        Analyze All
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($industry->isActive && count($companies) > 0): ?>
                <form method="post"
                      id="collect-form"
                      action="<?= Url::to(['collect', 'slug' => $industry->slug]) ?>">
                    <?= Html::hiddenInput(
                        Yii::$app->request->csrfParam,
                        Yii::$app->request->csrfToken
                    ) ?>
                    <button type="submit" class="btn btn--primary btn--sm">
                        Collect Data
                    </button>
                </form>
            <?php endif; ?>
            <a href="<?= Url::to(['ranking', 'slug' => $industry->slug]) ?>" class="btn btn--secondary btn--sm">
                View Rankings
            </a>
        </div>
    </div>
    <div class="card__body">
        <?php if (!$industry->isActive): ?>
            <div class="alert alert--warning">
                Activate this industry to enable data collection.
            </div>
        <?php elseif (count($companies) === 0): ?>
            <div class="alert alert--warning">
                Assign companies to this industry before collecting data.
            </div>
        <?php elseif (count($companies) < 2): ?>
            <div class="alert alert--warning">
                At least 2 companies are required for analysis.
            </div>
        <?php elseif (empty($runs)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No collection runs yet</h3>
                <p class="empty-state__text">Click "Collect Data" to start collecting financial data for this industry.</p>
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
        <form method="post" action="<?= Url::to(['add-members', 'slug' => $industry->slug]) ?>">
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

<!-- Collection Progress Modal -->
<div id="collect-modal" class="modal modal--hidden">
    <div class="modal__backdrop"></div>
    <div class="modal__content modal__content--narrow">
        <div class="modal__header">
            <h3 class="modal__title">Data Collection</h3>
        </div>
        <div class="modal__body">
            <div class="collect-phase">
                <span class="collect-phase__label">Initializing...</span>
            </div>

            <div class="progress-bar">
                <div class="progress-bar__fill" style="width: 0%"></div>
            </div>
            <div class="progress-bar__text">
                <span class="progress-bar__count">0 of 0 companies</span>
                <span class="progress-bar__time">Elapsed: 0s</span>
            </div>

            <div class="collect-result collect-result--hidden">
                <div class="collect-result__status">
                    <span class="badge"></span>
                </div>
                <div class="collect-result__summary"></div>
            </div>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--danger" id="cancel-collect-btn">
                Cancel
            </button>

            <a href="#" class="btn btn--primary btn--hidden" id="view-run-btn">
                View Results
            </a>
            <button type="button" class="btn btn--secondary btn--hidden" id="close-collect-btn">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Add Members Modal Logic
document.getElementById('add-members-btn').addEventListener('click', function() {
    document.getElementById('add-members-modal').classList.remove('modal--hidden');
});

function closeModal() {
    document.getElementById('add-members-modal').classList.add('modal--hidden');
}

// Collection Modal Logic
const collectForm = document.getElementById('collect-form');
const collectModal = document.getElementById('collect-modal');
let pollInterval = null;
let currentRunId = null;
let isCollecting = false;

if (collectForm) {
    collectForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (isCollecting) return;
        
        if (confirm('Start data collection for this industry?')) {
            startCollection();
        }
    });
}

async function startCollection() {
    isCollecting = true;
    showCollectModal();
    updatePhase('Initializing collection...');
    
    // Reset UI
    document.querySelector('.progress-bar__fill').style.width = '0%';
    document.querySelector('.collect-result').classList.add('collect-result--hidden');
    document.getElementById('cancel-collect-btn').classList.remove('btn--hidden');
    document.getElementById('view-run-btn').classList.add('btn--hidden');
    document.getElementById('close-collect-btn').classList.add('btn--hidden');

    try {
        const formData = new FormData(collectForm);
        const response = await fetch(collectForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success && data.runId) {
            currentRunId = data.runId;
            startPolling();
        } else {
            alert('Failed to start collection: ' + (data.errors ? data.errors.join(', ') : 'Unknown error'));
            closeCollectModal();
        }
    } catch (e) {
        console.error(e);
        alert('Network error starting collection');
        closeCollectModal();
    }
}

function startPolling() {
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(pollStatus, 2000);
}

async function pollStatus() {
    try {
        const response = await fetch(`/admin/collection-run/${currentRunId}/status`);
        const status = await response.json();

        updateProgress(status);

        if (['complete', 'failed', 'cancelled'].includes(status.status)) {
            clearInterval(pollInterval);
            pollInterval = null;
            isCollecting = false;
            showResult(status);
        }
    } catch (e) {
        console.error('Polling error', e);
    }
}

async function cancelCollection() {
    if (!confirm('Cancel data collection? Progress will be lost.')) return;

    try {
        // Optimistic UI update
        document.getElementById('cancel-collect-btn').disabled = true;
        updatePhase('Cancelling...');
        
        await fetch(`/admin/collection-run/${currentRunId}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': document.querySelector('[name="<?= Yii::$app->request->csrfParam ?>"]').value
            }
        });
    } catch (e) {
        console.error('Cancel error', e);
        document.getElementById('cancel-collect-btn').disabled = false;
    }
}

function updateProgress(status) {
    const total = status.companies_total || 1;
    const done = status.companies_success + status.companies_failed;
    const pct = Math.min(100, Math.round((done / total) * 100));

    document.querySelector('.progress-bar__fill').style.width = pct + '%';
    document.querySelector('.progress-bar__count').textContent = `${done} of ${total} companies`;
    document.querySelector('.progress-bar__time').textContent = `Elapsed: ${status.duration_seconds || 0}s`;

    if (status.cancel_requested) {
         updatePhase('Cancellation requested...');
    } else if (done < total) {
        updatePhase(`Collecting company ${done + 1} of ${total}...`);
    } else {
        updatePhase('Validating results...');
    }
}

function updatePhase(text) {
    document.querySelector('.collect-phase__label').textContent = text;
}

function showResult(status) {
    // Hide progress, show result
    document.querySelector('.collect-result').classList.remove('collect-result--hidden');
    document.getElementById('cancel-collect-btn').classList.add('btn--hidden');
    document.getElementById('cancel-collect-btn').disabled = false;
    
    document.getElementById('view-run-btn').href = `/admin/collection-run/${currentRunId}`;
    document.getElementById('view-run-btn').classList.remove('btn--hidden');
    document.getElementById('close-collect-btn').classList.remove('btn--hidden');

    // Set status badge
    const badge = document.querySelector('.collect-result__status .badge');
    const statusText = status.status.charAt(0).toUpperCase() + status.status.slice(1);
    badge.textContent = statusText;
    
    let badgeClass = 'badge--inactive';
    if (status.status === 'complete') {
        badgeClass = status.gate_passed ? 'badge--success' : 'badge--warning';
    } else if (status.status === 'failed') {
        badgeClass = 'badge--danger';
    } else if (status.status === 'cancelled') {
        badgeClass = 'badge--warning';
    }
    badge.className = 'badge ' + badgeClass;

    // Set summary
    const summary = document.querySelector('.collect-result__summary');
    summary.innerHTML = `
        <div>Companies: ${status.companies_success} success, ${status.companies_failed} failed</div>
        <div>Gate: ${status.gate_passed ? 'Passed' : 'Failed'}</div>
        <div>Duration: ${status.duration_seconds}s</div>
    `;
}

function showCollectModal() {
    collectModal.classList.remove('modal--hidden');
}

function closeCollectModal() {
    collectModal.classList.add('modal--hidden');
    isCollecting = false;
    if (pollInterval) clearInterval(pollInterval);
    location.reload();
}

document.getElementById('cancel-collect-btn')?.addEventListener('click', cancelCollection);
document.getElementById('close-collect-btn')?.addEventListener('click', closeCollectModal);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (collectModal && !collectModal.classList.contains('modal--hidden')) {
            if (!isCollecting) {
                 closeCollectModal();
            }
        } else {
            closeModal();
        }
    }
});
</script>
