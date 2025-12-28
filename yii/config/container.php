<?php

declare(strict_types=1);

use app\adapters\AdapterChain;
use app\adapters\BlockedSourceRegistry;
use app\adapters\CachedDataAdapter;
use app\adapters\SourceAdapterInterface;
use app\adapters\YahooFinanceAdapter;
use app\queries\DataPackRepository;
use yii\di\Container;

return [
    'definitions' => [
        BlockedSourceRegistry::class => BlockedSourceRegistry::class,

        CachedDataAdapter::class => static function (Container $container): CachedDataAdapter {
            return new CachedDataAdapter(
                $container->get(DataPackRepository::class),
                Yii::$app->request->getParam('industry', 'unknown'),
            );
        },

        AdapterChain::class => static function (Container $container): AdapterChain {
            return new AdapterChain(
                adapters: [
                    $container->get(YahooFinanceAdapter::class),
                    $container->get(CachedDataAdapter::class),
                ],
                blockedRegistry: $container->get(BlockedSourceRegistry::class),
                logger: Yii::getLogger(),
            );
        },

        SourceAdapterInterface::class => AdapterChain::class,
        YahooFinanceAdapter::class => YahooFinanceAdapter::class,

        DataPackRepository::class => static function (): DataPackRepository {
            $params = Yii::$app->params;
            $basePath = $params['datapacksPath'] ?? '@runtime/datapacks';

            return new DataPackRepository(
                basePath: Yii::getAlias($basePath),
            );
        },
    ],
    'singletons' => [],
];
