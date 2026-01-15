<?php

declare(strict_types=1);

use app\dto\pdf\CompanyRankingDto;

/**
 * Rating summary section for PDF.
 *
 * @var CompanyRankingDto[] $rankings
 */

$buyCompanies = array_filter($rankings, fn ($c) => $c->rating === 'buy');
$holdCompanies = array_filter($rankings, fn ($c) => $c->rating === 'hold');
$sellCompanies = array_filter($rankings, fn ($c) => $c->rating === 'sell');

$buyTickers = implode(', ', array_map(fn ($c) => $c->ticker, $buyCompanies));
$holdTickers = implode(', ', array_map(fn ($c) => $c->ticker, $holdCompanies));
$sellTickers = implode(', ', array_map(fn ($c) => $c->ticker, $sellCompanies));
?>
<section class="report__section">
    <h2 class="report__section-title">Rating Summary</h2>
    <div class="summary-grid">
        <div class="summary-row">
            <span class="badge badge--success">BUY</span>
            <span class="summary-count"><?= count($buyCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $buyTickers ?: '-' ?></span>
        </div>
        <div class="summary-row">
            <span class="badge badge--warning">HOLD</span>
            <span class="summary-count"><?= count($holdCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $holdTickers ?: '-' ?></span>
        </div>
        <div class="summary-row">
            <span class="badge badge--danger">SELL</span>
            <span class="summary-count"><?= count($sellCompanies) ?> companies</span>
            <span class="summary-tickers"><?= $sellTickers ?: '-' ?></span>
        </div>
    </div>
</section>
