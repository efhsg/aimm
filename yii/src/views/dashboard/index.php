<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array{total: int, active: int, inactive: int} $industryCounts
 * @var list<array<string, mixed>> $recentRuns
 * @var int $policyCount
 * @var array{total: int, complete: int, failed: int, running: int} $runStats
 */

$this->title = 'AIMM Dashboard';
?>

<div class="dashboard">
    <div class="dashboard__header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Equity Intelligence Pipeline Overview</p>
        </div>
        <div class="system-status-badge">
            <span class="status-dot"></span>
            System Operational
        </div>
    </div>

    <div class="dashboard__grid">
        <!-- Active Industries -->
        <a href="<?= Url::to(['/industry/index']) ?>" class="stat-card">
            <div class="stat-card__icon bg-brand-light">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Active Industries</div>
                <div class="stat-card__value"><?= $industryCounts['active'] ?></div>
                <div class="stat-card__meta">of <?= $industryCounts['total'] ?> total industries</div>
            </div>
        </a>

        <!-- Collection Policies -->
        <a href="<?= Url::to(['/collection-policy/index']) ?>" class="stat-card">
            <div class="stat-card__icon bg-brand-accent">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Collection Policies</div>
                <div class="stat-card__value"><?= $policyCount ?></div>
                <div class="stat-card__meta">Active definitions</div>
            </div>
        </a>

        <!-- Collection Runs -->
        <a href="<?= Url::to(['/collection-run/index']) ?>" class="stat-card">
            <div class="stat-card__icon bg-viz-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Collection Runs</div>
                <div class="stat-card__value"><?= $runStats['total'] ?></div>
                <div class="stat-card__meta">
                    <?php if ($runStats['running'] > 0): ?>
                        <span class="text-info"><?= $runStats['running'] ?> active</span>
                    <?php elseif ($runStats['failed'] > 0): ?>
                        <span class="text-error"><?= $runStats['failed'] ?> issues</span>
                    <?php else: ?>
                        <span class="text-success">All systems normal</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>

        <!-- System Health -->
        <div class="stat-card stat-card--health">
            <div class="stat-card__icon bg-success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="stat-card__content">
                <div class="stat-card__label">Pipeline Health</div>
                <div class="stat-card__value">100%</div>
                <div class="stat-card__meta">All services operational</div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentRuns)): ?>
    <div class="content-card">
        <div class="content-card__header">
            <h2 class="content-card__title">Recent Activity</h2>
            <a href="<?= Url::to(['/collection-run/index']) ?>" class="btn btn--secondary btn--sm">View All Runs</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Industry / Target</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th class="text-right">Duration</th>
                        <th class="text-right">Coverage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRuns as $run): ?>
                    <tr>
                        <td class="font-medium">
                            <a href="<?= Url::to(['/collection-run/view', 'id' => $run['id']]) ?>" class="link-reset">
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
                            <span class="badge <?= $statusClass ?>"><?= Html::encode(ucfirst($run['status'])) ?></span>
                        </td>
                        <td class="text-secondary"><?= Html::encode($run['started_at']) ?></td>
                        <td class="text-right font-mono">
                            <?php if ($run['duration_seconds']): ?>
                                <?= (int)$run['duration_seconds'] ?>s
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-right font-mono">
                            <?php if ($run['companies_total']): ?>
                                <span class="<?= $run['companies_success'] === $run['companies_total'] ? 'text-success' : '' ?>">
                                    <?= (int)$run['companies_success'] ?>/<?= (int)$run['companies_total'] ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.dashboard {
    padding: var(--space-6) 0;
    max-width: var(--container-xl);
    margin: 0 auto;
}

.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: var(--space-8);
    border-bottom: 1px solid var(--border-subtle);
    padding-bottom: var(--space-6);
}

.page-title {
    font-family: var(--font-sans);
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--brand-primary);
    margin: 0 0 var(--space-2) 0;
    letter-spacing: -0.02em;
}

.page-subtitle {
    font-size: var(--text-lg);
    color: var(--text-secondary);
    margin: 0;
}

.system-status-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    background: var(--color-success-light);
    color: var(--color-success);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
}

.status-dot {
    width: 8px;
    height: 8px;
    background-color: currentColor;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.dashboard__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: var(--space-6);
    margin-bottom: var(--space-10);
}

.stat-card {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    padding: var(--space-6);
    background: var(--bg-surface);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: inherit;
    transition: all var(--duration-fast) var(--easing-default);
    box-shadow: var(--shadow-sm);
}

.stat-card:hover {
    transform: translateY(-2px);
    border-color: var(--brand-primary);
    box-shadow: var(--shadow-md);
}

.stat-card--health {
    cursor: default;
    background: var(--bg-elevated);
}

.stat-card--health:hover {
    transform: none;
    border-color: var(--border-default);
    box-shadow: var(--shadow-sm);
}

.stat-card__icon {
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
    color: var(--text-inverse);
}

.bg-brand-light { background-color: var(--brand-light); }
.bg-brand-accent { background-color: var(--brand-accent); }
.bg-viz-1 { background-color: var(--viz-1); }
.bg-success { background-color: var(--color-success); }

.stat-card__icon svg {
    width: 24px;
    height: 24px;
}

.stat-card__content {
    flex: 1;
    min-width: 0;
}

.stat-card__label {
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    color: var(--text-secondary);
    margin-bottom: var(--space-1);
}

.stat-card__value {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--brand-primary);
    line-height: 1.1;
    margin-bottom: var(--space-2);
}

.stat-card__meta {
    font-size: var(--text-xs);
    color: var(--text-tertiary);
    font-family: var(--font-mono);
}

.content-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-default);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.content-card__header {
    padding: var(--space-6);
    border-bottom: 1px solid var(--border-subtle);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-card__title {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--text-primary);
    margin: 0;
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-sm);
}

.table th {
    text-align: left;
    padding: var(--space-3) var(--space-6);
    background: var(--bg-muted);
    color: var(--text-secondary);
    font-weight: var(--font-medium);
    text-transform: uppercase;
    font-size: var(--text-xs);
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border-default);
}

.table td {
    padding: var(--space-4) var(--space-6);
    border-bottom: 1px solid var(--border-subtle);
    color: var(--text-primary);
}

.table tr:last-child td {
    border-bottom: none;
}

.table tr:hover td {
    background-color: var(--bg-elevated);
}

.text-right { text-align: right; }
.font-medium { font-weight: var(--font-medium); }
.font-mono { font-family: var(--font-mono); }
.text-secondary { color: var(--text-secondary); }
.text-success { color: var(--color-success); }
.text-error { color: var(--color-error); }
.text-info { color: var(--color-info); }
.link-reset {
    color: inherit;
    text-decoration: none;
}
.link-reset:hover {
    color: var(--brand-primary);
    text-decoration: underline;
}

@media (max-width: 768px) {
    .dashboard__header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-4);
    }
}
</style>