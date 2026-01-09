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
    'defaultRoute' => 'dashboard/index',
    'aliases' => [
        '@app' => dirname(__DIR__) . '/src',
    ],
    'viewPath' => dirname(__DIR__) . '/src/views',
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('COOKIE_VALIDATION_KEY') ?: 'dev-only-change-me',
            'enableCsrfValidation' => true,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
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
                // Health check
                'health' => 'health/index',

                // Admin: Industry
                'admin/industry' => 'industry/index',
                'admin/industry/create' => 'industry/create',
                'admin/industry/<slug:[a-z0-9-]+>' => 'industry/view',
                'admin/industry/<slug:[a-z0-9-]+>/edit' => 'industry/update',
                'admin/industry/<slug:[a-z0-9-]+>/toggle' => 'industry/toggle',
                'admin/industry/<slug:[a-z0-9-]+>/add-members' => 'industry/add-members',
                'admin/industry/<slug:[a-z0-9-]+>/remove-member' => 'industry/remove-member',
                'admin/industry/<slug:[a-z0-9-]+>/collect' => 'industry/collect',
                'admin/industry/<slug:[a-z0-9-]+>/analyze' => 'industry/analyze',
                'admin/industry/<slug:[a-z0-9-]+>/ranking' => 'industry/ranking',
                'admin/industry/<slug:[a-z0-9-]+>/report/<id:\d+>' => 'industry/report',

                // Admin: Collection Run
                'admin/collection-run' => 'collection-run/index',
                'admin/collection-run/<id:\d+>' => 'collection-run/view',
                'admin/collection-run/<id:\d+>/status' => 'collection-run/status',

                // Admin: Collection Policy
                'admin/collection-policy' => 'collection-policy/index',
                'admin/collection-policy/create' => 'collection-policy/create',
                'admin/collection-policy/<slug:[a-z0-9-]+>' => 'collection-policy/view',
                'admin/collection-policy/<slug:[a-z0-9-]+>/edit' => 'collection-policy/update',
                'admin/collection-policy/<slug:[a-z0-9-]+>/delete' => 'collection-policy/delete',
                'admin/collection-policy/<slug:[a-z0-9-]+>/export' => 'collection-policy/export',
                'admin/collection-policy/<slug:[a-z0-9-]+>/set-default' => 'collection-policy/set-default',
                'admin/collection-policy/validate-json' => 'collection-policy/validate-json',

                // Report API
                'POST api/reports/generate' => 'report/generate',
                'GET api/jobs/<id:\d+>' => 'report/job-status',
                'GET api/reports/<reportId:[a-zA-Z0-9_-]+>/download' => 'report/download',

                // Report preview (dev only)
                'report/preview' => 'report/preview',
                'report/preview-full' => 'report/preview-full',
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
