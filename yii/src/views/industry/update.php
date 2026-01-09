<?php

declare(strict_types=1);

use app\dto\industry\IndustryResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var IndustryResponse $industry
 * @var string $name
 * @var string $description
 * @var int|null $policyId
 * @var array[] $policies
 * @var string[] $errors
 */

$this->title = 'Edit: ' . $industry->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['view', 'slug' => $industry->slug]) ?>" class="btn btn--secondary">
        Cancel
    </a>
</div>

<?= $this->render('_form', [
    'name' => $name,
    'slug' => $industry->slug,
    'sectorId' => $industry->sectorId,
    'sectorName' => $industry->sectorName,
    'description' => $description,
    'policyId' => $policyId,
    'policies' => $policies,
    'sectors' => [],
    'errors' => $errors,
    'isCreate' => false,
]) ?>
