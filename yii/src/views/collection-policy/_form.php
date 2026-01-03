<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var string $slug
 * @var string $name
 * @var string $description
 * @var int $historyYears
 * @var int $quartersToFetch
 * @var string $valuationMetrics
 * @var string $annualFinancialMetrics
 * @var string $quarterlyFinancialMetrics
 * @var string $operationalMetrics
 * @var string $commodityBenchmark
 * @var string $marginProxy
 * @var string $sectorIndex
 * @var string $requiredIndicators
 * @var string $optionalIndicators
 * @var string[] $errors
 * @var bool $isUpdate
 */
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert--error">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= Html::encode($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    <?= Html::hiddenInput(
        Yii::$app->request->csrfParam,
        Yii::$app->request->csrfToken
    ) ?>

    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Basic Information</h2>
        </div>
        <div class="card__body">
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="form-label">Name <span class="required">*</span></label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-input"
                           value="<?= Html::encode($name) ?>"
                           required
                           <?= !$isUpdate ? 'oninput="generateSlug()"' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="slug" class="form-label">Slug <span class="required">*</span></label>
                    <input type="text"
                           id="slug"
                           name="slug"
                           class="form-input"
                           value="<?= Html::encode($slug) ?>"
                           pattern="[a-z0-9-]+"
                           required
                           <?= $isUpdate ? 'readonly' : '' ?>>
                    <p class="form-help">URL-safe identifier (lowercase, numbers, hyphens only)</p>
                </div>
            </div>

            <div class="form-group">
                <label for="description" class="form-label">Description</label>
                <textarea id="description"
                          name="description"
                          class="form-textarea"
                          rows="2"><?= Html::encode($description) ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="history_years" class="form-label">History Years</label>
                    <input type="number"
                           id="history_years"
                           name="history_years"
                           class="form-input"
                           value="<?= $historyYears ?>"
                           min="1"
                           max="20">
                </div>

                <div class="form-group">
                    <label for="quarters_to_fetch" class="form-label">Quarters to Fetch</label>
                    <input type="number"
                           id="quarters_to_fetch"
                           name="quarters_to_fetch"
                           class="form-input"
                           value="<?= $quartersToFetch ?>"
                           min="1"
                           max="20">
                </div>
            </div>
        </div>
    </div>

    <div class="card card--spaced">
        <div class="card__header">
            <h2 class="card__title">Macro Requirements</h2>
        </div>
        <div class="card__body">
            <div class="form-row form-row--3">
                <div class="form-group">
                    <label for="commodity_benchmark" class="form-label">Commodity Benchmark</label>
                    <input type="text"
                           id="commodity_benchmark"
                           name="commodity_benchmark"
                           class="form-input"
                           value="<?= Html::encode($commodityBenchmark) ?>"
                           placeholder="e.g., CLOIL">
                </div>

                <div class="form-group">
                    <label for="margin_proxy" class="form-label">Margin Proxy</label>
                    <input type="text"
                           id="margin_proxy"
                           name="margin_proxy"
                           class="form-input"
                           value="<?= Html::encode($marginProxy) ?>"
                           placeholder="e.g., CRACK321">
                </div>

                <div class="form-group">
                    <label for="sector_index" class="form-label">Sector Index</label>
                    <input type="text"
                           id="sector_index"
                           name="sector_index"
                           class="form-input"
                           value="<?= Html::encode($sectorIndex) ?>"
                           placeholder="e.g., XLE">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="required_indicators" class="form-label">Required Indicators (JSON array)</label>
                    <textarea id="required_indicators"
                              name="required_indicators"
                              class="form-textarea form-textarea--code"
                              rows="4"
                              placeholder='["FEDFUNDS", "CPI"]'><?= Html::encode($requiredIndicators) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="optional_indicators" class="form-label">Optional Indicators (JSON array)</label>
                    <textarea id="optional_indicators"
                              name="optional_indicators"
                              class="form-textarea form-textarea--code"
                              rows="4"
                              placeholder='["VIX"]'><?= Html::encode($optionalIndicators) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card card--spaced">
        <div class="card__header">
            <div>
                <h2 class="card__title">Data Requirements</h2>
                <p class="card__subtitle">Define metrics as JSON arrays with key, unit, required, and required_scope fields.</p>
            </div>
        </div>
        <div class="card__body">
            <div class="form-group">
                <label for="valuation_metrics" class="form-label">Valuation Metrics</label>
                <textarea id="valuation_metrics"
                          name="valuation_metrics"
                          class="form-textarea form-textarea--code"
                          rows="6"><?= Html::encode($valuationMetrics) ?></textarea>
            </div>

            <div class="form-group">
                <label for="annual_financial_metrics" class="form-label">Annual Financial Metrics</label>
                <textarea id="annual_financial_metrics"
                          name="annual_financial_metrics"
                          class="form-textarea form-textarea--code"
                          rows="6"><?= Html::encode($annualFinancialMetrics) ?></textarea>
            </div>

            <div class="form-group">
                <label for="quarterly_financial_metrics" class="form-label">Quarterly Financial Metrics</label>
                <textarea id="quarterly_financial_metrics"
                          name="quarterly_financial_metrics"
                          class="form-textarea form-textarea--code"
                          rows="6"><?= Html::encode($quarterlyFinancialMetrics) ?></textarea>
            </div>

            <div class="form-group">
                <label for="operational_metrics" class="form-label">Operational Metrics</label>
                <textarea id="operational_metrics"
                          name="operational_metrics"
                          class="form-textarea form-textarea--code"
                          rows="6"><?= Html::encode($operationalMetrics) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            <?= $isUpdate ? 'Update Policy' : 'Create Policy' ?>
        </button>
    </div>
</form>

<?php if (!$isUpdate): ?>
<script>
function generateSlug() {
    const name = document.getElementById('name').value;
    const slug = name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    document.getElementById('slug').value = slug;
}
</script>
<?php endif; ?>
