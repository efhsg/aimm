<?php

use yii\caching\FileCache;
use yii\log\FileTarget;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . '/container.php';

return [
    'id' => 'aimm-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\commands',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'components' => [
        'db' => $db,
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/app.log',
                ],
            ],
        ],
    ],
    'params' => $params,
    'container' => $container,
];

