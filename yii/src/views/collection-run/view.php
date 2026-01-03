<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array{id: int, industry_id: string, datapack_id: string, status: string, started_at: string, completed_at: ?string, companies_total: int, companies_success: int, companies_failed: int, gate_passed: ?int, error_count: int, warning_count: int, file_path: ?string, file_size_bytes: int, duration_seconds: int} $run
 * @var array{id: int, severity: string, error_code: string, error_message: string, error_path: ?string, ticker: ?string}[] $errors
 * @var array{id: int, severity: string, error_code: string, error_message: string, error_path: ?string, ticker: ?string}[] $warnings
 */

$this->title = 'Collection Run #' . $run['id'];

$statusClass = match ($run['status']) {
    'complete' => ((bool)$run['gate_passed']) ? 'badge--success' : 'badge--warning',
    'running' => 'badge--info',
    'failed' => 'badge--danger',
    default => 'badge--inactive',
};
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['peer-group/index']) ?>" class="btn btn--secondary">
            Back to Peer Groups
        </a>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <h2 class="card__title">Run Details</h2>
        <span class="badge <?= $statusClass ?>">
            <?= Html::encode(ucfirst($run['status'])) ?>
            <?php if ($run['status'] === 'complete' && (bool)$run['gate_passed']): ?>
                (Gate Passed)
            <?php elseif ($run['status'] === 'complete' && !(bool)$run['gate_passed']): ?>
                (Gate Failed)
            <?php endif; ?>
        </span>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Industry / Peer Group</div>
            <div class="detail-value"><?= Html::encode($run['industry_id']) ?></div>

            <div class="detail-label">Datapack ID</div>
            <div class="detail-value">
                <code><?= Html::encode($run['datapack_id']) ?></code>
            </div>

            <div class="detail-label">Started</div>
            <div class="detail-value"><?= Html::encode($run['started_at']) ?></div>

            <div class="detail-label">Completed</div>
            <div class="detail-value">
                <?= $run['completed_at'] !== null ? Html::encode($run['completed_at']) : '<span class="text-muted">In progress...</span>' ?>
            </div>

            <div class="detail-label">Duration</div>
            <div class="detail-value">
                <?= $run['duration_seconds'] > 0 ? $run['duration_seconds'] . 's' : '-' ?>
            </div>

            <div class="detail-label">Companies</div>
            <div class="detail-value">
                <span class="text-success"><?= $run['companies_success'] ?> success</span>,
                <span class="text-danger"><?= $run['companies_failed'] ?> failed</span>
                <?php if ($run['companies_total'] > 0): ?>
                    / <?= $run['companies_total'] ?> total
                <?php endif; ?>
            </div>

            <?php if (!empty($run['file_path'])): ?>
                <div class="detail-label">Output File</div>
                <div class="detail-value">
                    <code><?= Html::encode($run['file_path']) ?></code>
                    <?php if ($run['file_size_bytes'] > 0): ?>
                        <span class="text-muted">(<?= round($run['file_size_bytes'] / 1024, 1) ?> KB)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Errors (<?= count($errors) ?>)</h2>
    </div>
    <div class="card__body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticker</th>
                        <th>Code</th>
                        <th>Message</th>
                        <th>Path</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $error): ?>
                        <tr>
                            <td class="table__cell--mono">
                                <?= $error['ticker'] !== null ? Html::encode($error['ticker']) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="table__cell--mono"><code><?= Html::encode($error['error_code']) ?></code></td>
                            <td><?= Html::encode($error['error_message']) ?></td>
                            <td>
                                <?php if ($error['error_path'] !== null): ?>
                                    <code class="text-sm"><?= Html::encode($error['error_path']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($warnings)): ?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Warnings (<?= count($warnings) ?>)</h2>
    </div>
    <div class="card__body">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticker</th>
                        <th>Code</th>
                        <th>Message</th>
                        <th>Path</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warnings as $warning): ?>
                        <tr>
                            <td class="table__cell--mono">
                                <?= $warning['ticker'] !== null ? Html::encode($warning['ticker']) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="table__cell--mono"><code><?= Html::encode($warning['error_code']) ?></code></td>
                            <td><?= Html::encode($warning['error_message']) ?></td>
                            <td>
                                <?php if ($warning['error_path'] !== null): ?>
                                    <code class="text-sm"><?= Html::encode($warning['error_path']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($errors) && empty($warnings) && $run['status'] === 'complete'): ?>
<div class="card card--spaced">
    <div class="card__body">
        <div class="empty-state">
            <h3 class="empty-state__title">No issues found</h3>
            <p class="empty-state__text">This collection run completed without any errors or warnings.</p>
        </div>
    </div>
</div>
<?php endif; ?>
