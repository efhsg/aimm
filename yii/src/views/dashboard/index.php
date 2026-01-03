<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array{total: int, active: int, inactive: int} $peerGroupCounts
 * @var list<array<string, mixed>> $recentRuns
 * @var int $policyCount
 * @var array{total: int, complete: int, failed: int, running: int} $runStats
 */

$this->title = 'AIMM Dashboard';
?>

<div class="dashboard">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Equity Intelligence Pipeline</p>

    <div class="dashboard__cards">
        <a href="<?= Url::to(['/peer-group/index']) ?>" class="dashboard__card">
            <div class="dashboard__card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="dashboard__card-content">
                <div class="dashboard__card-value"><?= $peerGroupCounts['active'] ?></div>
                <div class="dashboard__card-label">Active Peer Groups</div>
                <div class="dashboard__card-detail"><?= $peerGroupCounts['total'] ?> total</div>
            </div>
        </a>

        <a href="<?= Url::to(['/collection-policy/index']) ?>" class="dashboard__card">
            <div class="dashboard__card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <div class="dashboard__card-content">
                <div class="dashboard__card-value"><?= $policyCount ?></div>
                <div class="dashboard__card-label">Collection Policies</div>
                <div class="dashboard__card-detail">Data requirements</div>
            </div>
        </a>

        <a href="<?= Url::to(['/collection-run/index']) ?>" class="dashboard__card">
            <div class="dashboard__card-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div class="dashboard__card-content">
                <div class="dashboard__card-value"><?= $runStats['total'] ?></div>
                <div class="dashboard__card-label">Collection Runs</div>
                <div class="dashboard__card-detail">
                    <?php if ($runStats['running'] > 0): ?>
                        <span class="badge badge--info"><?= $runStats['running'] ?> running</span>
                    <?php endif; ?>
                    <?php if ($runStats['failed'] > 0): ?>
                        <span class="badge badge--error"><?= $runStats['failed'] ?> failed</span>
                    <?php endif; ?>
                    <?php if ($runStats['complete'] > 0): ?>
                        <span class="badge badge--success"><?= $runStats['complete'] ?> complete</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>

        <div class="dashboard__card dashboard__card--status">
            <div class="dashboard__card-icon dashboard__card-icon--success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="dashboard__card-content">
                <div class="dashboard__card-value">Healthy</div>
                <div class="dashboard__card-label">System Status</div>
                <div class="dashboard__card-detail">All services operational</div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentRuns)): ?>
    <div class="dashboard__section">
        <div class="dashboard__section-header">
            <h2 class="dashboard__section-title">Recent Collection Runs</h2>
            <a href="<?= Url::to(['/collection-run/index']) ?>" class="btn btn--secondary btn--sm">View All</a>
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Industry</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Companies</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRuns as $run): ?>
                <tr>
                    <td>
                        <a href="<?= Url::to(['/collection-run/view', 'id' => $run['id']]) ?>">
                            <?= Html::encode($run['industry_id']) ?>
                        </a>
                    </td>
                    <td>
                        <?php
                        $statusClass = match($run['status']) {
                            'complete' => 'badge--success',
                            'failed' => 'badge--error',
                            'running' => 'badge--info',
                            default => 'badge--default',
                        };
                    ?>
                        <span class="badge <?= $statusClass ?>"><?= Html::encode($run['status']) ?></span>
                    </td>
                    <td><?= Html::encode($run['started_at']) ?></td>
                    <td>
                        <?php if ($run['duration_seconds']): ?>
                            <?= (int)$run['duration_seconds'] ?>s
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($run['companies_total']): ?>
                            <?= (int)$run['companies_success'] ?>/<?= (int)$run['companies_total'] ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.dashboard {
    padding: var(--space-6) 0;
}

.page-title {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    margin: 0 0 var(--space-2) 0;
}

.page-subtitle {
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    margin: 0 0 var(--space-8) 0;
}

.dashboard__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--space-6);
    margin-bottom: var(--space-10);
}

.dashboard__card {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    padding: var(--space-6);
    background: var(--surface-primary);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: inherit;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.dashboard__card:hover {
    border-color: var(--brand-primary);
    box-shadow: var(--shadow-md);
}

.dashboard__card--status {
    cursor: default;
}

.dashboard__card--status:hover {
    border-color: var(--border-default);
    box-shadow: none;
}

.dashboard__card-icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface-secondary);
    border-radius: var(--radius-md);
    color: var(--brand-primary);
}

.dashboard__card-icon svg {
    width: 24px;
    height: 24px;
}

.dashboard__card-icon--success {
    background: var(--success-bg);
    color: var(--success-text);
}

.dashboard__card-content {
    flex: 1;
    min-width: 0;
}

.dashboard__card-value {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    line-height: 1.2;
}

.dashboard__card-label {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    margin-top: var(--space-1);
}

.dashboard__card-detail {
    font-size: var(--font-size-xs);
    color: var(--text-tertiary);
    margin-top: var(--space-2);
}

.dashboard__section {
    background: var(--surface-primary);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
}

.dashboard__section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-4);
}

.dashboard__section-title {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    margin: 0;
}
</style>
