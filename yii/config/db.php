<?php

declare(strict_types=1);

use yii\db\Connection;

$host = getenv('DB_HOST') ?: 'aimm_mysql';
$database = getenv('DB_DATABASE') ?: 'aimm';

if ((defined('YII_ENV') && YII_ENV === 'test') || getenv('YII_ENV') === 'test') {
    $database = getenv('DB_DATABASE_TEST') ?: 'aimm_test';
}

return [
    'class' => Connection::class,
    'dsn' => sprintf('mysql:host=%s;dbname=%s', $host, $database),
    'username' => getenv('DB_USER') ?: 'aimm',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'tablePrefix' => 'aimm_',
];
