<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var string $content
 */

$this->beginPage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title ?? 'AIMM') ?></title>
    <link rel="stylesheet" href="<?= Url::to('@web/css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= Url::to('@web/css/admin.css') ?>">
    <?php $this->head(); ?>
</head>
<body>
<?php $this->beginBody(); ?>

<header class="admin-header">
    <div class="admin-header__container">
        <a href="<?= Url::to(['/dashboard/index']) ?>" class="admin-header__brand">
            <svg viewBox="0 0 543 474" xmlns="http://www.w3.org/2000/svg" class="admin-header__logo" aria-hidden="true" focusable="false">
                <defs>
                    <linearGradient id="logoGradientA" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#1a4a55"/><stop offset="100%" stop-color="#0d2a32"/></linearGradient>
                    <linearGradient id="logoGradientB" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#1a5565"/><stop offset="100%" stop-color="#4ab8d5"/></linearGradient>
                </defs>
                <path fill="url(#logoGradientA)" d="M90.3,417C78,412.6,67,400.5,63.9,387.9c-3.4-13.7-1.5-19.9,14.3-48.7c7.9-14.4,17.2-31.3,20.5-37.6c3.4-6.2,10.7-19.4,16.2-29.3c5.5-9.9,21.3-38.4,35.1-63.3c13.8-24.9,26.6-48,28.4-51.3c10.8-19.1,38.6-70.1,45-82.4c6.9-13.4,14.5-21.4,25.5-27c8.2-4.2,10.1-4.6,20.4-4.6c9.8,0,12.5,0.5,19.2,3.8c13.7,6.7,19.3,13.6,35.5,44.2c3.1,5.9,9,16.5,12.9,23.7c4,7.2,7,13.8,6.6,14.7c-0.3,0.9-5.6,7.2-11.7,13.9l-11,12.4l-8.2-15c-4.5-8.3-11.4-20.7-15.3-27.7c-3.9-7-9.4-17-12.3-22.3c-8.2-15-13.8-18-22.3-12c-4.5,3.1-5.1,4.1-23.6,38.9c-6.3,11.8-11.9,22.1-12.5,22.8c-1,1.3-13.2,23.3-30.9,56c-4.4,8.1-11.7,21.3-16.3,29.3c-9.6,16.9-19.3,34.3-32.1,58c-5,9.2-11.6,21.2-14.7,26.7c-3.1,5.5-12,21.8-19.8,36.2c-21,38.9-21.3,38.5,18.2,38.5h27.4l6.3-11.7c9.2-17,36.5-65.5,40.2-71.4l3.2-5l3.6,4.3c6,7.1,18.4,15.4,28,18.7c12.3,4.2,31.1,4.2,41.4,0c18.2-7.4,32.3-21.2,46.9-45.7c9.3-15.7,20.2-29.9,38.6-50.5c17.4-19.5,18.4-20.4,20-19.4c0.9,0.5,4.5,6.8,8,13.9c5.8,11.6,15.8,30.1,53.1,98.9c27,49.7,26.4,48.2,26.3,61.2c0,9.3-0.6,12.5-3,17.1c-4.8,9.1-11.1,15.2-19.6,19.2l-8,3.8h-83.1l-12-21.7c-6.6-11.9-16.3-29.6-21.6-39.3c-5.3-9.7-9.8-17.7-10.1-17.7c-0.2,0-4.8,2.4-10.2,5.2c-17.8,9.5-24.7,11.2-45.8,11.3c-17.1,0-19.5-0.3-28.7-3.7c-5.5-2-10.9-4-12-4.3c-1.2-0.4-2.8,0.8-4.1,3.1c-1.2,2.1-9.8,18-19.3,35.4l-17.2,31.7l-42.1-0.1C121.6,418.8,95.1,418.8,90.3,417z M437.9,383.5c1.2-1.2,2.1-3.7,2.1-5.7c0-3.8-0.1-3.9-25.8-51.7c-9.5-17.5-21.5-39.8-26.7-49.5c-5.2-9.7-9.8-17.6-10.3-17.7c-0.4,0-6.1,8.5-12.6,19c-6.5,10.5-14.4,23-17.6,27.8l-5.8,8.8l16.6,29.8c9.1,16.4,17.9,32.4,19.4,35.5l2.9,5.7h27.9C431.5,385.6,436.8,384.7,437.9,383.5z"/>
                <path fill="url(#logoGradientB)" d="M256.1,283.3c-16.5-16.8-50-52.3-50.4-53.4c-0.2-0.5,4.8-6,11-12.3l11.4-11.3l20.3,20.4l20.3,20.4l3.3-3.9c1.8-2.1,9.9-11.1,18-19.9c8.1-8.8,25.2-27.6,38-41.8c12.8-14.2,34.3-37.8,47.8-52.4l24.4-26.6l-7-7.1c-3.8-3.9-10.5-9.9-14.7-13.4c-4.2-3.5-7.3-6.8-6.8-7.2c0.5-0.5,21-5.2,45.6-10.5c24.6-5.3,46.4-10.2,48.5-10.8l3.8-1.2l-0.9,7.7c-1.4,11.9-14.3,91.6-14.9,92.2c-0.3,0.3-6.8-5.5-14.4-13c-7.6-7.5-14.7-13.6-15.8-13.6c-1.1,0-2.5,1.2-3.2,2.6c-0.7,1.5-12.4,14.8-26,29.7c-13.7,14.9-43.6,47.7-66.5,73c-44.1,48.6-58.4,64-59.7,64C261.6,289.5,256.5,288.7,256.1,283.3z"/>
            </svg>
            <div class="brand__text">
                <span class="brand__name">AIMM</span>
                <span class="brand__tagline">Equity Intelligence Pipeline</span>
            </div>
        </a>
        <nav class="admin-header__nav">
            <a href="<?= Url::to(['/industry/index']) ?>" class="admin-nav__link">
                Admin
            </a>
        </nav>
    </div>
</header>

<main class="admin-main">
    <div class="admin-container">
        <?= $content ?>
    </div>
</main>

<footer class="admin-footer">
    <div class="admin-container">
        <p class="admin-footer__text">AIMM - Equity Intelligence Pipeline</p>
    </div>
</footer>

<?php $this->endBody(); ?>
</body>
</html>
<?php $this->endPage(); ?>
