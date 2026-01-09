<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $name
 * @var string $slug
 * @var int|null $sectorId
 * @var string $description
 * @var int|null $policyId
 * @var array[] $policies
 * @var array[] $sectors
 * @var string[] $errors
 */

$this->title = 'Create Industry';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['index']) ?>" class="btn btn--secondary">
        Cancel
    </a>
</div>

<?= $this->render('_form', [
    'name' => $name,
    'slug' => $slug,
    'sectorId' => $sectorId,
    'description' => $description,
    'policyId' => $policyId,
    'policies' => $policies,
    'sectors' => $sectors,
    'errors' => $errors,
    'isCreate' => true,
]) ?>
