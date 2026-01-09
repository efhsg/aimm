<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var string $label
 * @var string $column
 * @var string $currentOrder
 * @var string $currentDir
 * @var string|null $currentStatus
 * @var string|null $currentSearch
 * @var int|null $currentSectorId
 */

$isActive = $currentOrder === $column;
$newDir = $isActive && $currentDir === 'ASC' ? 'DESC' : 'ASC';

$params = ['index', 'order' => $column, 'dir' => $newDir];
if ($currentStatus !== null) {
    $params['status'] = $currentStatus;
}
if ($currentSearch !== null) {
    $params['search'] = $currentSearch;
}
if ($currentSectorId !== null) {
    $params['sector'] = $currentSectorId;
}
?>
<a href="<?= Url::to($params) ?>" class="table__sort">
    <?= Html::encode($label) ?>
    <?php if ($isActive): ?>
        <?= $currentDir === 'ASC' ? '↑' : '↓' ?>
    <?php endif; ?>
</a>
