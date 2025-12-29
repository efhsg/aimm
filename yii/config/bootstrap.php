<?php

declare(strict_types=1);

use yii\di\Container;

$containerConfig = require __DIR__ . '/container.php';

Yii::$container = new Container();

foreach ($containerConfig['singletons'] as $class => $definition) {
    Yii::$container->setSingleton($class, $definition);
}

foreach ($containerConfig['definitions'] as $class => $definition) {
    Yii::$container->set($class, $definition);
}
