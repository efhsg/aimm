<?php

use yii\web\Application;

defined('YII_DEBUG') || define('YII_DEBUG', getenv('YII_DEBUG') === '1');
defined('YII_ENV') || define('YII_ENV', getenv('YII_ENV') ?: 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new Application($config))->run();

