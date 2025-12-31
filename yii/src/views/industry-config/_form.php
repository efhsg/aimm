<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $industryId
 * @var string $configJson
 * @var string[] $errors
 * @var bool $isCreate
 */

$actionUrl = $isCreate
    ? Url::to(['create'])
    : Url::to(['update', 'industry_id' => $industryId]);
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
        <form method="post" action="<?= $actionUrl ?>" id="config-form">
            <?= Html::hiddenInput(
                Yii::$app->request->csrfParam,
                Yii::$app->request->csrfToken
            ) ?>

            <?php if ($isCreate): ?>
                <div class="form-group">
                    <label for="industry_id" class="form-label">Industry ID</label>
                    <input type="text"
                           id="industry_id"
                           name="industry_id"
                           class="form-input"
                           value="<?= Html::encode($industryId) ?>"
                           placeholder="e.g., oilfield_services"
                           required>
                    <p class="form-help">
                        Unique identifier using lowercase letters, numbers, and underscores.
                        Must match the "id" field in the JSON configuration.
                    </p>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label class="form-label">Industry ID</label>
                    <input type="text"
                           class="form-input"
                           value="<?= Html::encode($industryId) ?>"
                           readonly>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="config_json" class="form-label">Configuration JSON</label>
                <div class="toolbar">
                    <button type="button" class="btn btn--sm btn--secondary" id="format-json-btn">
                        Format JSON
                    </button>
                    <button type="button" class="btn btn--sm btn--secondary" id="validate-json-btn">
                        Validate
                    </button>
                    <span id="validation-status"></span>
                </div>
                <textarea id="config_json"
                          name="config_json"
                          class="form-textarea"
                          placeholder="Enter JSON configuration..."
                          required><?= Html::encode($configJson) ?></textarea>
                <p class="form-help">
                    JSON must conform to the industry-config schema.
                    The "id" field must match the Industry ID.
                </p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--primary">
                    <?= $isCreate ? 'Create Configuration' : 'Save Changes' ?>
                </button>
                <a href="<?= $isCreate ? Url::to(['index']) : Url::to(['view', 'industry_id' => $industryId]) ?>"
                   class="btn btn--secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('config_json');
    const formatBtn = document.getElementById('format-json-btn');
    const validateBtn = document.getElementById('validate-json-btn');
    const validationStatus = document.getElementById('validation-status');
    const industryIdInput = document.getElementById('industry_id');

    formatBtn.addEventListener('click', function() {
        try {
            const parsed = JSON.parse(textarea.value);
            textarea.value = JSON.stringify(parsed, null, 2);
            textarea.classList.remove('form-textarea--error');
        } catch (e) {
            alert('Invalid JSON: ' + e.message);
            textarea.classList.add('form-textarea--error');
        }
    });

    validateBtn.addEventListener('click', function() {
        const configJson = textarea.value;
        const industryId = industryIdInput ? industryIdInput.value : '<?= Html::encode($industryId) ?>';

        validationStatus.innerHTML = '<span style="color: var(--text-secondary);">Validating...</span>';

        const formData = new FormData();
        formData.append('config_json', configJson);
        formData.append('industry_id', industryId);
        formData.append('<?= Yii::$app->request->csrfParam ?>', '<?= Yii::$app->request->csrfToken ?>');

        fetch('<?= Url::to(['validate-json']) ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.valid) {
                validationStatus.innerHTML = '<span class="badge badge--valid">Valid</span>';
                textarea.classList.remove('form-textarea--error');
            } else {
                validationStatus.innerHTML = '<span class="badge badge--invalid">Invalid</span>';
                textarea.classList.add('form-textarea--error');
                alert('Validation errors:\n' + data.errors.join('\n'));
            }
        })
        .catch(error => {
            validationStatus.innerHTML = '<span class="badge badge--invalid">Error</span>';
            alert('Validation request failed: ' + error.message);
        });
    });
});
</script>
