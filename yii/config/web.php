<?php

declare(strict_types=1);

use yii\caching\FileCache;
use yii\log\FileTarget;
use yii\web\UrlNormalizer;

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
    'viewPath' => dirname(__DIR__) . '/src/views',
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'dev-only-change-me',
            'enableCsrfValidation' => true,
        ],
        'session' => [
            'class' => 'yii\web\Session',
            'cookieParams' => [
                'httponly' => true,
                'secure' => (getenv('YII_ENV') === 'prod'),
                'sameSite' => 'Lax',
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'normalizer' => [
                'class' => UrlNormalizer::class,
                'collapseSlashes' => true,
                'normalizeTrailingSlash' => true,
            ],
            'rules' => [
                'industry-config' => 'industry-config/index',
                'industry-config/create' => 'industry-config/create',
                'industry-config/view/<industry_id:[a-z0-9_-]+>' => 'industry-config/view',
                'industry-config/update/<industry_id:[a-z0-9_-]+>' => 'industry-config/update',
                'industry-config/toggle/<industry_id:[a-z0-9_-]+>' => 'industry-config/toggle',
                'industry-config/validate-json' => 'industry-config/validate-json',
            ],
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
