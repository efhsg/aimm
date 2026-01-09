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
                <p class="empty-state__text">Create a policy to define data collection requirements for industries.</p>
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
                            <th>Created</th>
                            <th>Actions</th>
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
                                <td class="table__cell--mono"><code><?= Html::encode($policy['slug']) ?></code></td>
                                <td class="table__cell--number"><?= $policy['history_years'] ?> years</td>
                                <td class="table__cell--number"><?= $policy['quarters_to_fetch'] ?></td>
                                <td>
                                    <?= Html::encode($policy['created_at']) ?>
                                    <?php if (!empty($policy['created_by'])): ?>
                                        <br>
                                        <span class="text-muted text-sm">by <?= Html::encode($policy['created_by']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table__actions">
                                        <a href="<?= Url::to(['view', 'slug' => $policy['slug']]) ?>"
                                           class="btn btn--sm btn--secondary">
                                            View
                                        </a>
                                        <a href="<?= Url::to(['update', 'slug' => $policy['slug']]) ?>"
                                           class="btn btn--sm btn--secondary">
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
