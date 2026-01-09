<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array{id: int, industry_id: string, datapack_id: string, status: string, started_at: string, completed_at: ?string, companies_total: int, companies_success: int, companies_failed: int, gate_passed: ?int, error_count: int, warning_count: int, file_path: ?string, file_size_bytes: int, duration_seconds: int} $run
 * @var array{id: int, severity: string, error_code: string, error_message: string, error_path: ?string, ticker: ?string}[] $errors
 * @var array{id: int, severity: string, error_code: string, error_message: string, error_path: ?string, ticker: ?string}[] $warnings
 * @var array[] $companies
 * @var array[] $annualFinancials
 * @var array[] $valuations
 * @var array[] $macroIndicators
 * @var list<int> $availableYears
 * @var list<string> $availableValuationDates
 * @var list<string> $availableMacroDates
 */

/** Format large numbers for display */
function formatNumber(?float $value, int $decimals = 0): string
{
    if ($value === null) {
        return '-';
    }
    if (abs($value) >= 1_000_000_000) {
        return number_format($value / 1_000_000_000, 1) . 'B';
    }
    if (abs($value) >= 1_000_000) {
        return number_format($value / 1_000_000, 1) . 'M';
    }
    return number_format($value, $decimals);
}

/** Format percentage */
function formatPercent(?float $value): string
{
    if ($value === null) {
        return '-';
    }
    return number_format($value * 100, 2) . '%';
}

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
        <a href="<?= Url::to(['industry/index']) ?>" class="btn btn--secondary">
            Back to Industries
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
            <div class="detail-label">Industry</div>
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

<?php if (!empty($availableYears)): ?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Annual Financials</h2>
        <select id="financials-year" class="form-select form-select--inline" onchange="loadFinancials(this.value)">
            <?php foreach ($availableYears as $year): ?>
                <option value="<?= $year ?>"><?= $year ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="card__body">
        <div class="table-container">
            <table class="table" id="financials-table">
                <thead>
                    <tr>
                        <th>Ticker</th>
                        <th>Year</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">EBITDA</th>
                        <th class="text-right">Net Income</th>
                        <th class="text-right">FCF</th>
                        <th class="text-right">Net Debt</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="financials-tbody">
                    <?php foreach ($annualFinancials as $fin): ?>
                        <tr>
                            <td class="table__cell--mono"><strong><?= Html::encode($fin['ticker']) ?></strong></td>
                            <td><?= Html::encode($fin['fiscal_year']) ?></td>
                            <td class="text-right"><?= formatNumber((float)($fin['revenue'] ?? 0)) ?></td>
                            <td class="text-right"><?= formatNumber((float)($fin['ebitda'] ?? 0)) ?></td>
                            <td class="text-right"><?= formatNumber((float)($fin['net_income'] ?? 0)) ?></td>
                            <td class="text-right"><?= formatNumber((float)($fin['free_cash_flow'] ?? 0)) ?></td>
                            <td class="text-right"><?= formatNumber((float)($fin['net_debt'] ?? 0)) ?></td>
                            <td><code class="text-sm"><?= Html::encode($fin['source_adapter'] ?? '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($availableValuationDates)): ?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Valuation Snapshots</h2>
        <select id="valuations-date" class="form-select form-select--inline" onchange="loadValuations(this.value)">
            <?php foreach ($availableValuationDates as $date): ?>
                <option value="<?= Html::encode($date) ?>"><?= Html::encode($date) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="card__body">
        <div class="table-container">
            <table class="table" id="valuations-table">
                <thead>
                    <tr>
                        <th>Ticker</th>
                        <th>Date</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Market Cap</th>
                        <th class="text-right">Fwd P/E</th>
                        <th class="text-right">EV/EBITDA</th>
                        <th class="text-right">Div Yield</th>
                        <th class="text-right">FCF Yield</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="valuations-tbody">
                    <?php foreach ($valuations as $val): ?>
                        <tr>
                            <td class="table__cell--mono"><strong><?= Html::encode($val['ticker']) ?></strong></td>
                            <td><?= Html::encode($val['snapshot_date']) ?></td>
                            <td class="text-right">$<?= number_format((float)($val['price'] ?? 0), 2) ?></td>
                            <td class="text-right"><?= formatNumber((float)($val['market_cap'] ?? 0)) ?></td>
                            <td class="text-right"><?= number_format((float)($val['forward_pe'] ?? 0), 2) ?></td>
                            <td class="text-right"><?= number_format((float)($val['ev_to_ebitda'] ?? 0), 2) ?></td>
                            <td class="text-right"><?= formatPercent((float)($val['dividend_yield'] ?? 0)) ?></td>
                            <td class="text-right"><?= formatPercent((float)($val['fcf_yield'] ?? 0)) ?></td>
                            <td><code class="text-sm"><?= Html::encode($val['source_adapter'] ?? '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($availableMacroDates)): ?>
<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Macro Indicators</h2>
        <select id="macro-date" class="form-select form-select--inline" onchange="loadMacro(this.value)">
            <?php foreach ($availableMacroDates as $date): ?>
                <option value="<?= Html::encode($date) ?>"><?= Html::encode($date) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="card__body">
        <div class="table-container">
            <table class="table" id="macro-table">
                <thead>
                    <tr>
                        <th>Indicator</th>
                        <th>Date</th>
                        <th class="text-right">Value</th>
                        <th>Unit</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody id="macro-tbody">
                    <?php foreach ($macroIndicators as $indicator): ?>
                        <tr>
                            <td class="table__cell--mono"><strong><?= Html::encode($indicator['indicator_key']) ?></strong></td>
                            <td><?= Html::encode($indicator['indicator_date']) ?></td>
                            <td class="text-right"><?= number_format((float)($indicator['value'] ?? 0), 2) ?></td>
                            <td><?= Html::encode($indicator['unit'] ?? '-') ?></td>
                            <td><code class="text-sm"><?= Html::encode($indicator['source_adapter'] ?? '-') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($availableYears) && empty($availableValuationDates) && empty($availableMacroDates)): ?>
<div class="card card--spaced">
    <div class="card__body">
        <div class="empty-state">
            <h3 class="empty-state__title">No collected data</h3>
            <p class="empty-state__text">No financial data, valuations, or macro indicators found for this industry.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const runId = <?= (int)$run['id'] ?>;
const dataUrl = '<?= Url::to(['collection-run/data']) ?>';

function formatNumber(value) {
    if (value === null || value === undefined) return '-';
    const num = parseFloat(value);
    if (Math.abs(num) >= 1e9) return (num / 1e9).toFixed(1) + 'B';
    if (Math.abs(num) >= 1e6) return (num / 1e6).toFixed(1) + 'M';
    return num.toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function formatPercent(value) {
    if (value === null || value === undefined) return '-';
    return (parseFloat(value) * 100).toFixed(2) + '%';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function loadFinancials(year) {
    const tbody = document.getElementById('financials-tbody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>';

    try {
        const response = await fetch(`${dataUrl}?id=${runId}&type=financials&filter=${year}`);
        const result = await response.json();

        if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No data for this year</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(fin => `
            <tr>
                <td class="table__cell--mono"><strong>${escapeHtml(fin.ticker)}</strong></td>
                <td>${escapeHtml(fin.fiscal_year)}</td>
                <td class="text-right">${formatNumber(fin.revenue)}</td>
                <td class="text-right">${formatNumber(fin.ebitda)}</td>
                <td class="text-right">${formatNumber(fin.net_income)}</td>
                <td class="text-right">${formatNumber(fin.free_cash_flow)}</td>
                <td class="text-right">${formatNumber(fin.net_debt)}</td>
                <td><code class="text-sm">${escapeHtml(fin.source_adapter || '-')}</code></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading data</td></tr>';
    }
}

async function loadValuations(date) {
    const tbody = document.getElementById('valuations-tbody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Loading...</td></tr>';

    try {
        const response = await fetch(`${dataUrl}?id=${runId}&type=valuations&filter=${date}`);
        const result = await response.json();

        if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No data for this date</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(val => `
            <tr>
                <td class="table__cell--mono"><strong>${escapeHtml(val.ticker)}</strong></td>
                <td>${escapeHtml(val.snapshot_date)}</td>
                <td class="text-right">$${parseFloat(val.price || 0).toFixed(2)}</td>
                <td class="text-right">${formatNumber(val.market_cap)}</td>
                <td class="text-right">${parseFloat(val.forward_pe || 0).toFixed(2)}</td>
                <td class="text-right">${parseFloat(val.ev_to_ebitda || 0).toFixed(2)}</td>
                <td class="text-right">${formatPercent(val.dividend_yield)}</td>
                <td class="text-right">${formatPercent(val.fcf_yield)}</td>
                <td><code class="text-sm">${escapeHtml(val.source_adapter || '-')}</code></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading data</td></tr>';
    }
}

async function loadMacro(date) {
    const tbody = document.getElementById('macro-tbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>';

    try {
        const response = await fetch(`${dataUrl}?id=${runId}&type=macro&filter=${date}`);
        const result = await response.json();

        if (result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No data for this date</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(ind => `
            <tr>
                <td class="table__cell--mono"><strong>${escapeHtml(ind.indicator_key)}</strong></td>
                <td>${escapeHtml(ind.indicator_date)}</td>
                <td class="text-right">${parseFloat(ind.value || 0).toFixed(2)}</td>
                <td>${escapeHtml(ind.unit || '-')}</td>
                <td><code class="text-sm">${escapeHtml(ind.source_adapter || '-')}</code></td>
            </tr>
        `).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
    }
}
</script>
