<?php

declare(strict_types=1);

use app\clients\DatabaseRateLimiter;
use app\clients\RateLimiterInterface;
use app\queries\SourceBlockRepository;
use yii\di\Container;

$baseConfig = require __DIR__ . '/container.php';

$baseConfig['singletons'][RateLimiterInterface::class] = static function (
    Container $container
): RateLimiterInterface {
    return new DatabaseRateLimiter(
        $container->get(SourceBlockRepository::class),
    );
};

return $baseConfig;
