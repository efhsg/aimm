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
 * @var string|null $currentSector
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
if ($currentSector !== null) {
    $params['sector'] = $currentSector;
}
?>
<a href="<?= Url::to($params) ?>" style="text-decoration: none; color: inherit;">
    <?= Html::encode($label) ?>
    <?php if ($isActive): ?>
        <?= $currentDir === 'ASC' ? '↑' : '↓' ?>
    <?php endif; ?>
</a>
