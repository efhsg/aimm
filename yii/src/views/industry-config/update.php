<?php

declare(strict_types=1);

use app\dto\industryconfig\IndustryConfigResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryConfigResponse $config
 * @var string $configJson
 * @var string[] $errors
 */

$this->title = 'Edit: ' . $config->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['view', 'industry_id' => $config->industryId]) ?>" class="btn btn--secondary">
            Cancel
        </a>
    </div>
</div>

<?= $this->render('_form', [
    'industryId' => $config->industryId,
    'configJson' => $configJson,
    'errors' => $errors,
    'isCreate' => false,
]) ?>
