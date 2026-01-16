<?php

declare(strict_types=1);

use app\dto\industry\IndustryResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryResponse $industry
 * @var array<string, mixed> $reportRow
 * @var array<string, mixed> $report
 */

$this->title = "Report - {$industry->name}";
$metadata = $report['metadata'];
$companyAnalyses = $report['company_analyses'];
$groupAverages = $report['group_averages'];
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['ranking', 'slug' => $industry->slug]) ?>" class="btn btn--primary">
            View Latest
        </a>
        <a href="<?= Url::to(['view', 'slug' => $industry->slug]) ?>" class="btn btn--secondary">
            Back to Industry
        </a>
    </div>
</div>

<div class="card">
    <div class="card__header">
        <h2 class="card__title">Report Details</h2>
    </div>
    <div class="card__body">
        <div class="detail-grid detail-grid--4col">
            <div class="detail-label">Companies Analyzed</div>
            <div class="detail-value"><?= $metadata['company_count'] ?></div>

            <div class="detail-label">Generated</div>
            <div class="detail-value"><?= isset($metadata['generated_at']) ? (new DateTimeImmutable($metadata['generated_at']))->format('M j, Y H:i') : '-' ?></div>

            <div class="detail-label">Data As Of</div>
            <div class="detail-value"><?= isset($metadata['data_as_of']) ? (new DateTimeImmutable($metadata['data_as_of']))->format('M j, Y H:i') : '-' ?></div>

            <div class="detail-label">Report ID</div>
            <div class="detail-value table__cell--mono"><?= Html::encode($metadata['report_id']) ?></div>
        </div>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Industry Averages</h2>
    </div>
    <div class="card__body">
        <div class="detail-grid detail-grid--4col">
            <div class="detail-label">Fwd P/E</div>
            <div class="detail-value">
                <?= $groupAverages['fwd_pe'] !== null ? number_format($groupAverages['fwd_pe'], 1) . 'x' : '-' ?>
            </div>

            <div class="detail-label">EV/EBITDA</div>
            <div class="detail-value">
                <?= $groupAverages['ev_ebitda'] !== null ? number_format($groupAverages['ev_ebitda'], 1) . 'x' : '-' ?>
            </div>

            <div class="detail-label">FCF Yield</div>
            <div class="detail-value">
                <?= $groupAverages['fcf_yield_percent'] !== null ? number_format($groupAverages['fcf_yield_percent'], 1) . '%' : '-' ?>
            </div>

            <div class="detail-label">Div Yield</div>
            <div class="detail-value">
                <?= $groupAverages['div_yield_percent'] !== null ? number_format($groupAverages['div_yield_percent'], 2) . '%' : '-' ?>
            </div>
        </div>
    </div>
</div>

<div class="card card--spaced">
    <div class="card__header">
        <h2 class="card__title">Company Rankings</h2>
    </div>
    <div class="card__body">
        <?php if (empty($companyAnalyses)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No companies analyzed</h3>
                <p class="empty-state__text">This report has no company analysis data.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Ticker</th>
                            <th>Company</th>
                            <th>Rating</th>
                            <th>Fundamentals</th>
                            <th>Risk</th>
                            <th>Valuation Gap</th>
                            <th>Market Cap</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companyAnalyses as $analysis): ?>
                            <?php
                            $ratingClass = match ($analysis['rating']) {
                                'buy' => 'badge--success',
                                'sell' => 'badge--danger',
                                default => 'badge--warning',
                            };
                            $fundamentalsClass = match ($analysis['fundamentals']['assessment']) {
                                'improving' => 'badge--success',
                                'deteriorating' => 'badge--danger',
                                default => 'badge--warning',
                            };
                            $riskClass = match ($analysis['risk']['assessment']) {
                                'acceptable' => 'badge--success',
                                'unacceptable' => 'badge--danger',
                                default => 'badge--warning',
                            };
                            $gapDirection = $analysis['valuation_gap']['direction'] ?? null;
                            $gapClass = match ($gapDirection) {
                                'undervalued' => 'text-success',
                                'overvalued' => 'text-danger',
                                default => '',
                            };
                            ?>
                            <tr>
                                <td class="table__cell--number">
                                    <strong>#<?= $analysis['rank'] ?></strong>
                                </td>
                                <td class="table__cell--mono">
                                    <strong><?= Html::encode($analysis['ticker']) ?></strong>
                                </td>
                                <td><?= Html::encode($analysis['name']) ?></td>
                                <td>
                                    <span class="badge <?= $ratingClass ?>">
                                        <?= Html::encode(strtoupper($analysis['rating'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $fundamentalsClass ?>">
                                        <?= Html::encode(ucfirst($analysis['fundamentals']['assessment'])) ?>
                                    </span>
                                    <small class="text-muted">
                                        (<?= number_format($analysis['fundamentals']['composite_score'], 2) ?>)
                                    </small>
                                </td>
                                <td>
                                    <span class="badge <?= $riskClass ?>">
                                        <?= Html::encode(ucfirst($analysis['risk']['assessment'])) ?>
                                    </span>
                                </td>
                                <td class="table__cell--number <?= $gapClass ?>">
                                    <?php
                                    $gap = $analysis['valuation_gap']['composite_gap'];
                            if ($gap !== null): ?>
                                        <?= $gap > 0 ? '+' : '' ?><?= number_format($gap, 1) ?>%
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table__cell--number">
                                    <?php
                            $marketCap = $analysis['valuation']['market_cap_billions'];
                            if ($marketCap !== null): ?>
                                        $<?= number_format($marketCap, 1) ?>B
                                    <?php else: ?>
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
