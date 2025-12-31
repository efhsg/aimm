<?php

declare(strict_types=1);

use app\dto\industryconfig\IndustryConfigResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryConfigResponse $config
 * @var bool $jsonValid
 * @var string[] $jsonErrors
 */

$this->title = $config->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['update', 'industry_id' => $config->industryId]) ?>" class="btn btn--primary">
            Edit Configuration
        </a>
        <form method="post"
              action="<?= Url::to(['toggle', 'industry_id' => $config->industryId]) ?>"
              style="display: inline;">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>
            <input type="hidden" name="return_url" value="<?= Url::to(['view', 'industry_id' => $config->industryId]) ?>">
            <button type="submit"
                    class="btn <?= $config->isActive ? 'btn--danger' : 'btn--success' ?>">
                <?= $config->isActive ? 'Disable' : 'Enable' ?>
            </button>
        </form>
        <a href="<?= Url::to(['index']) ?>" class="btn btn--secondary">
            Back to List
        </a>
    </div>
</div>

<?php if (!$jsonValid): ?>
    <div class="validation-errors">
        <h4 class="validation-errors__title">Configuration Validation Errors</h4>
        <ul class="validation-errors__list">
            <?php foreach ($jsonErrors as $error): ?>
                <li><?= Html::encode($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__header">
        <h2 class="card__title">Details</h2>
        <span class="badge <?= $config->isActive ? 'badge--active' : 'badge--inactive' ?>">
            <?= $config->isActive ? 'Active' : 'Inactive' ?>
        </span>
    </div>
    <div class="card__body">
        <div class="detail-grid">
            <div class="detail-label">Industry ID</div>
            <div class="detail-value"><?= Html::encode($config->industryId) ?></div>

            <div class="detail-label">Name</div>
            <div class="detail-value"><?= Html::encode($config->name) ?></div>

            <div class="detail-label">JSON Valid</div>
            <div class="detail-value">
                <span class="badge <?= $jsonValid ? 'badge--valid' : 'badge--invalid' ?>">
                    <?= $jsonValid ? 'Valid' : 'Invalid' ?>
                </span>
            </div>

            <div class="detail-label">Created</div>
            <div class="detail-value">
                <?= Html::encode($config->createdAt->format('Y-m-d H:i:s')) ?>
                <?php if ($config->createdBy !== null): ?>
                    by <?= Html::encode($config->createdBy) ?>
                <?php endif; ?>
            </div>

            <div class="detail-label">Updated</div>
            <div class="detail-value">
                <?= Html::encode($config->updatedAt->format('Y-m-d H:i:s')) ?>
                <?php if ($config->updatedBy !== null): ?>
                    by <?= Html::encode($config->updatedBy) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: var(--space-6);">
    <div class="card__header">
        <h2 class="card__title">Configuration JSON</h2>
    </div>
    <div class="card__body">
        <div class="json-display">
            <pre><?= Html::encode(json_encode(
                json_decode($config->configJson),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )) ?></pre>
        </div>
    </div>
</div>
