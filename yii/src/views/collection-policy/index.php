<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed>[] $policies
 */

$this->title = 'Collection Policies';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
            + Create Policy
        </a>
    </div>
</div>

<div class="card">
    <div class="card__body">
        <?php if (empty($policies)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No collection policies</h3>
                <p class="empty-state__text">Create a policy to define data collection requirements for peer groups.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>History</th>
                            <th>Quarters</th>
                            <th>Sector Default</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($policies as $policy): ?>
                            <tr>
                                <td>
                                    <a href="<?= Url::to(['view', 'slug' => $policy['slug']]) ?>">
                                        <strong><?= Html::encode($policy['name']) ?></strong>
                                    </a>
                                    <?php if (!empty($policy['description'])): ?>
                                        <br>
                                        <span class="text-muted text-sm"><?= Html::encode($policy['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= Html::encode($policy['slug']) ?></code></td>
                                <td><?= $policy['history_years'] ?> years</td>
                                <td><?= $policy['quarters_to_fetch'] ?></td>
                                <td>
                                    <?php if (!empty($policy['is_default_for_sector'])): ?>
                                        <span class="badge badge--info"><?= Html::encode($policy['is_default_for_sector']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= Html::encode($policy['created_at']) ?>
                                    <?php if (!empty($policy['created_by'])): ?>
                                        <br>
                                        <span class="text-muted text-sm">by <?= Html::encode($policy['created_by']) ?></span>
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

<style>
.text-sm {
    font-size: var(--text-sm);
}
code {
    font-family: var(--font-mono);
    background: var(--bg-subtle);
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius-sm);
}
</style>
