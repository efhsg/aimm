<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $industryId
 * @var string $configJson
 * @var string[] $errors
 */

$this->title = 'Create Industry Configuration';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['index']) ?>" class="btn btn--secondary">
        Cancel
    </a>
</div>

<?= $this->render('_form', [
    'industryId' => $industryId,
    'configJson' => $configJson,
    'errors' => $errors,
    'isCreate' => true,
]) ?>
