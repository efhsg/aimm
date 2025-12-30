<?php

declare(strict_types=1);

use yii\caching\FileCache;
use yii\log\FileTarget;

$params = require __DIR__ . '/params.php';
$localParamsPath = __DIR__ . '/params-local.php';
if (file_exists($localParamsPath)) {
    $params = array_replace_recursive($params, require $localParamsPath);
}
$db = require __DIR__ . '/db.php';
$container = require __DIR__ . (YII_ENV_PROD ? '/container-production.php' : '/container.php');

return [
    'id' => 'aimm-web',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\controllers',
    'defaultRoute' => 'health/index',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'dev-only-change-me',
        ],
        'db' => $db,
        'cache' => [
            'class' => FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => FileTarget::class,
                    'levels' => ['error', 'warning', 'info'],
                    'logFile' => '@runtime/logs/web.log',
                ],
            ],
        ],
    ],
    'params' => $params,
    'container' => $container,
];
