<?php

declare(strict_types=1);

use app\dto\pdf\CompanyRankingDto;

/**
 * Company rankings table for PDF.
 *
 * @var CompanyRankingDto[] $rankings
 */
?>
<section class="report__section">
    <h2 class="report__section-title">Company Rankings</h2>
    <?php if (empty($rankings)): ?>
        <p class="text-muted">No companies analyzed.</p>
    <?php else: ?>
        <table class="report-table">
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
                <?php foreach ($rankings as $company): ?>
                    <tr>
                        <td class="col-numeric"><strong>#<?= $company->rank ?></strong></td>
                        <td class="col-mono"><strong><?= htmlspecialchars($company->ticker, ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars($company->name, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge <?= $company->getRatingBadgeClass() ?>">
                                <?= strtoupper($company->rating) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $company->getFundamentalsBadgeClass() ?>">
                                <?= ucfirst($company->fundamentalsAssessment) ?>
                            </span>
                            <small class="text-muted">(<?= number_format($company->fundamentalsScore, 2) ?>)</small>
                        </td>
                        <td>
                            <span class="badge <?= $company->getRiskBadgeClass() ?>">
                                <?= ucfirst($company->riskAssessment) ?>
                            </span>
                        </td>
                        <td class="col-numeric <?= $company->getGapClass() ?>">
                            <?= $company->formatValuationGap() ?>
                        </td>
                        <td class="col-numeric">
                            <?= $company->formatMarketCap() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
