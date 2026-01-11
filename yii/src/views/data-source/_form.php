<?php

declare(strict_types=1);

use app\models\DataSource;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $id
 * @var string $name
 * @var string $sourceType
 * @var string $baseUrl
 * @var string $notes
 * @var string[] $errors
 * @var bool $isCreate
 */
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--danger mb-4">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= Html::encode($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="form">
    <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->getCsrfToken()) ?>

    <div class="card">
        <div class="card__body">
            <div class="form-group">
                <label class="form-label" for="id">ID</label>
                <input type="text" id="id" name="id" value="<?= Html::encode($id) ?>"
                       class="form-input" <?= $isCreate ? '' : 'readonly' ?> required>
                <?php if ($isCreate): ?>
                    <p class="form-hint">Unique identifier (e.g., 'fmp', 'yahoo_finance'). Cannot be changed later.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= Html::encode($name) ?>"
                       class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="source_type">Source Type</label>
                <select id="source_type" name="source_type" class="form-select" required>
                    <option value="<?= DataSource::SOURCE_TYPE_API ?>" <?= $sourceType === DataSource::SOURCE_TYPE_API ? 'selected' : '' ?>>API</option>
                    <option value="<?= DataSource::SOURCE_TYPE_WEB_SCRAPE ?>" <?= $sourceType === DataSource::SOURCE_TYPE_WEB_SCRAPE ? 'selected' : '' ?>>Web Scrape</option>
                    <option value="<?= DataSource::SOURCE_TYPE_DERIVED ?>" <?= $sourceType === DataSource::SOURCE_TYPE_DERIVED ? 'selected' : '' ?>>Derived</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="base_url">Base URL</label>
                <input type="url" id="base_url" name="base_url" value="<?= Html::encode($baseUrl) ?>"
                       class="form-input" placeholder="https://api.example.com">
                <p class="form-hint">Optional. Root URL for API or website.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="4"><?= Html::encode($notes) ?></textarea>
                <p class="form-hint">Documentation about this data source.</p>
            </div>
        </div>
        <div class="card__footer">
            <div class="flex gap-2">
                <button type="submit" class="btn btn--primary">
                    <?= $isCreate ? 'Create Data Source' : 'Update Data Source' ?>
                </button>
                <a href="<?= $isCreate ? Url::to(['index']) : Url::to(['view', 'id' => $id]) ?>" class="btn btn--secondary">
                    Cancel
                </a>
            </div>
        </div>
    </div>
</form>
