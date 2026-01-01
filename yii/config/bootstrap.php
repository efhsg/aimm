<?php

declare(strict_types=1);

use app\events\QuarterlyFinancialsCollectedEvent;
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\dossier\RecalculateTtmOnQuarterlyCollected;
use yii\base\Event;
use yii\di\Container;

$containerConfig = require __DIR__ . '/container.php';

Yii::$container = new Container();

foreach ($containerConfig['singletons'] as $class => $definition) {
    Yii::$container->setSingleton($class, $definition);
}

foreach ($containerConfig['definitions'] as $class => $definition) {
    Yii::$container->set($class, $definition);
}

// Event Wiring
Event::on(
    CollectCompanyHandler::class,
    'quarterly_financials_collected',
    function ($event) {
        if ($event instanceof QuarterlyFinancialsCollectedEvent) {
            $handler = Yii::$container->get(RecalculateTtmOnQuarterlyCollected::class);
            $handler->handle($event);
        }
    }
);
