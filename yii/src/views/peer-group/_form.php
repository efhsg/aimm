<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $name
 * @var string $slug
 * @var string $sector
 * @var string $description
 * @var int|null $policyId
 * @var array[] $policies
 * @var string[] $errors
 * @var bool $isCreate
 */

$actionUrl = $isCreate
    ? Url::to(['create'])
    : Url::to(['update', 'slug' => $slug]);
?>

<?php if (!empty($errors)): ?>
    <div class="validation-errors">
        <h4 class="validation-errors__title">Validation Errors</h4>
        <ul class="validation-errors__list">
            <?php foreach ($errors as $error): ?>
                <li><?= Html::encode($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card__body">
        <form method="post" action="<?= $actionUrl ?>" id="peer-group-form">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>

            <div class="form-group">
                <label for="name" class="form-label">Name *</label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-input"
                       value="<?= Html::encode($name) ?>"
                       placeholder="e.g., Global Energy Supermajors"
                       required>
            </div>

            <?php if ($isCreate): ?>
                <div class="form-group">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text"
                           id="slug"
                           name="slug"
                           class="form-input"
                           value="<?= Html::encode($slug) ?>"
                           placeholder="auto-generated from name if empty"
                           pattern="[a-z0-9-]+">
                    <p class="form-help">
                        URL-safe identifier. Lowercase letters, numbers, and hyphens only.
                        Leave empty to auto-generate from name.
                    </p>
                </div>

                <div class="form-group">
                    <label for="sector" class="form-label">Sector *</label>
                    <input type="text"
                           id="sector"
                           name="sector"
                           class="form-input"
                           value="<?= Html::encode($sector) ?>"
                           placeholder="e.g., Energy"
                           required>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input type="text"
                           class="form-input"
                           value="<?= Html::encode($slug) ?>"
                           readonly>
                </div>

                <div class="form-group">
                    <label class="form-label">Sector</label>
                    <input type="text"
                           class="form-input"
                           value="<?= Html::encode($sector) ?>"
                           readonly>
                    <p class="form-help">Sector cannot be changed after creation.</p>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description"
                          name="description"
                          class="form-textarea"
                          rows="3"
                          placeholder="Optional description of this peer group..."><?= Html::encode($description) ?></textarea>
            </div>

            <div class="form-group">
                <label for="policy_id" class="form-label">Collection Policy</label>
                <select id="policy_id" name="policy_id" class="form-input">
                    <option value="">-- No Policy --</option>
                    <?php foreach ($policies as $policy): ?>
                        <option value="<?= Html::encode($policy['id']) ?>"
                                <?= $policyId !== null && (int) $policy['id'] === $policyId ? 'selected' : '' ?>>
                            <?= Html::encode($policy['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">
                    Assign a collection policy to define what data to collect for this group.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">
                    <?= $isCreate ? 'Create Peer Group' : 'Save Changes' ?>
                </button>
                <a href="<?= $isCreate ? Url::to(['index']) : Url::to(['view', 'slug' => $slug]) ?>"
                   class="btn btn--secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($isCreate): ?>
<script>
document.getElementById('name').addEventListener('blur', function() {
    const slugInput = document.getElementById('slug');
    if (slugInput.value === '') {
        const name = this.value;
        const slug = name.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        slugInput.value = slug;
    }
});
</script>
<?php endif; ?>
