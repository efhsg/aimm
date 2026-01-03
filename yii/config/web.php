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
                // Peer Group
                'peer-group' => 'peer-group/index',
                'peer-group/create' => 'peer-group/create',
                'peer-group/<slug:[a-z0-9-]+>' => 'peer-group/view',
                'peer-group/<slug:[a-z0-9-]+>/edit' => 'peer-group/update',
                'peer-group/<slug:[a-z0-9-]+>/toggle' => 'peer-group/toggle',
                'peer-group/<slug:[a-z0-9-]+>/add-members' => 'peer-group/add-members',
                'peer-group/<slug:[a-z0-9-]+>/remove-member' => 'peer-group/remove-member',
                'peer-group/<slug:[a-z0-9-]+>/set-focal' => 'peer-group/set-focal',
                'peer-group/<slug:[a-z0-9-]+>/add-focal' => 'peer-group/add-focal',
                'peer-group/<slug:[a-z0-9-]+>/remove-focal' => 'peer-group/remove-focal',
                'peer-group/<slug:[a-z0-9-]+>/clear-focals' => 'peer-group/clear-focals',
                'peer-group/<slug:[a-z0-9-]+>/collect' => 'peer-group/collect',

                // Collection Run
                'collection-run/<id:\d+>' => 'collection-run/view',
                'collection-run/<id:\d+>/status' => 'collection-run/status',

                // Collection Policy
                'collection-policy' => 'collection-policy/index',
                'collection-policy/create' => 'collection-policy/create',
                'collection-policy/<slug:[a-z0-9-]+>' => 'collection-policy/view',
                'collection-policy/<slug:[a-z0-9-]+>/edit' => 'collection-policy/update',
                'collection-policy/<slug:[a-z0-9-]+>/delete' => 'collection-policy/delete',
                'collection-policy/<slug:[a-z0-9-]+>/export' => 'collection-policy/export',
                'collection-policy/<slug:[a-z0-9-]+>/set-default' => 'collection-policy/set-default',
                'collection-policy/validate-json' => 'collection-policy/validate-json',
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
