<?php

declare(strict_types=1);

use yii\db\Connection;

$host = getenv('DB_HOST') ?: 'aimm_mysql';
$database = getenv('DB_DATABASE') ?: 'aimm';

return [
    'class' => Connection::class,
    'dsn' => sprintf('mysql:host=%s;dbname=%s', $host, $database),
    'username' => getenv('DB_USER') ?: 'aimm',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'tablePrefix' => 'aimm_',
];
