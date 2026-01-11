<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed> $policy
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
 * @var array<string, string[]> $sourcePriorities
 * @var array<string, mixed>[] $dataSources
 * @var string[] $errors
 */

$this->title = 'Edit: ' . $policy['name'];
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['view', 'slug' => $policy['slug']]) ?>" class="btn btn--secondary">
            Cancel
        </a>
    </div>
</div>

<?= $this->render('_form', [
    'slug' => $policy['slug'],
    'name' => $name,
    'description' => $description,
    'historyYears' => $historyYears,
    'quartersToFetch' => $quartersToFetch,
    'valuationMetrics' => $valuationMetrics,
    'annualFinancialMetrics' => $annualFinancialMetrics,
    'quarterlyFinancialMetrics' => $quarterlyFinancialMetrics,
    'operationalMetrics' => $operationalMetrics,
    'commodityBenchmark' => $commodityBenchmark,
    'marginProxy' => $marginProxy,
    'sectorIndex' => $sectorIndex,
    'requiredIndicators' => $requiredIndicators,
    'optionalIndicators' => $optionalIndicators,
    'sourcePriorities' => $sourcePriorities,
    'dataSources' => $dataSources,
    'errors' => $errors,
    'isUpdate' => true,
]) ?>
