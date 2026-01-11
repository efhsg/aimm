<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var string $id
 * @var string $name
 * @var string $sourceType
 * @var string $baseUrl
 * @var string $notes
 * @var string[] $errors
 */

$this->title = 'Create Data Source';
$this->params['breadcrumbs'][] = ['label' => 'Data Sources', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
</div>

<div class="container-sm">
    <?= $this->render('_form', [
        'id' => $id,
        'name' => $name,
        'sourceType' => $sourceType,
        'baseUrl' => $baseUrl,
        'notes' => $notes,
        'errors' => $errors,
        'isCreate' => true,
    ]) ?>
</div>
