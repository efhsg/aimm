<?php

declare(strict_types=1);

use app\log\SanitizedFileTarget;
use yii\caching\FileCache;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . (YII_ENV_PROD ? '/container-production.php' : '/container.php');

return [
    'id' => 'aimm-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\\commands',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'controllerMap' => [
        'migrate' => [
            'class' => \yii\console\controllers\MigrateController::class,
            'migrationPath' => dirname(__DIR__) . '/migrations',
        ],
        'collect' => [
            'class' => \app\commands\CollectController::class,
        ],
    ],
    'components' => [
        'db' => $db,
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => SanitizedFileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'categories' => ['collection', 'application', 'alerts'],
                    'logFile' => '@runtime/logs/collection.log',
                    'maxFileSize' => 10240,
                    'maxLogFiles' => 10,
                ],
            ],
        ],
    ],
    'params' => $params,
    'container' => $container,
];
