<?php

declare(strict_types=1);

use app\dto\peergroup\PeerGroupResponse;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var PeerGroupResponse $group
 * @var string $name
 * @var string $description
 * @var int|null $policyId
 * @var array[] $policies
 * @var string[] $errors
 */

$this->title = 'Edit: ' . $group->name;
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <a href="<?= Url::to(['view', 'slug' => $group->slug]) ?>" class="btn btn--secondary">
        Cancel
    </a>
</div>

<?= $this->render('_form', [
    'name' => $name,
    'slug' => $group->slug,
    'sector' => $group->sector,
    'description' => $description,
    'policyId' => $policyId,
    'policies' => $policies,
    'errors' => $errors,
    'isCreate' => false,
]) ?>
