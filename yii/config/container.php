<?php

declare(strict_types=1);

use app\adapters\AdapterChain;
use app\adapters\BlockedSourceRegistry;
use app\adapters\CachedDataAdapter;
use app\adapters\SourceAdapterInterface;
use app\adapters\YahooFinanceAdapter;
use app\alerts\AlertDispatcher;
use app\clients\AllowedDomainPolicy;
use app\clients\AllowedDomainPolicyInterface;
use app\clients\BlockDetector;
use app\clients\BlockDetectorInterface;
use app\clients\FileRateLimiter;
use app\clients\GuzzleWebFetchClient;
use app\clients\RandomUserAgentProvider;
use app\clients\RateLimiterInterface;
use app\clients\UserAgentProviderInterface;
use app\clients\WebFetchClientInterface;
use app\factories\CompanyDataFactory;
use app\factories\DataPointFactory;
use app\factories\IndustryDataPackFactory;
use app\factories\SourceCandidateFactory;
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\collection\CollectCompanyInterface;
use app\handlers\collection\CollectDatapointHandler;
use app\handlers\collection\CollectDatapointInterface;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectIndustryInterface;
use app\handlers\collection\CollectMacroHandler;
use app\handlers\collection\CollectMacroInterface;
use app\queries\DataPackRepository;
use app\queries\IndustryConfigQuery;
use app\transformers\DataPackAssembler;
use app\transformers\DataPackAssemblerInterface;
use app\validators\CollectionGateValidator;
use app\validators\CollectionGateValidatorInterface;
use app\validators\SchemaValidator;
use app\validators\SchemaValidatorInterface;
use app\validators\SemanticValidator;
use app\validators\SemanticValidatorInterface;
use GuzzleHttp\Client;
use Yii;
use yii\di\Container;
use yii\log\Logger;

return [
    'singletons' => [
        RateLimiterInterface::class => static function (): RateLimiterInterface {
            return new FileRateLimiter(Yii::getAlias('@runtime/ratelimit'));
        },
        BlockDetectorInterface::class => BlockDetector::class,
        AllowedDomainPolicyInterface::class => AllowedDomainPolicy::class,
        SchemaValidatorInterface::class => static function (): SchemaValidatorInterface {
            return new SchemaValidator(Yii::$app->basePath . '/config/schemas');
        },
        Logger::class => static function (): Logger {
            return Yii::getLogger();
        },
    ],
    'definitions' => [
        UserAgentProviderInterface::class => RandomUserAgentProvider::class,

        WebFetchClientInterface::class => static function (Container $container): WebFetchClientInterface {
            $params = Yii::$app->params;
            $timeout = $params['httpTimeout'] ?? 30;
            $connectTimeout = $params['httpConnectTimeout'] ?? 10;

            return new GuzzleWebFetchClient(
                httpClient: new Client([
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                    'verify' => true,
                    'http_errors' => false,
                ]),
                rateLimiter: $container->get(RateLimiterInterface::class),
                userAgentProvider: $container->get(UserAgentProviderInterface::class),
                blockDetector: $container->get(BlockDetectorInterface::class),
                allowedDomainPolicy: $container->get(AllowedDomainPolicyInterface::class),
                alertDispatcher: $container->get(AlertDispatcher::class),
                logger: $container->get(Logger::class),
            );
        },

        AlertDispatcher::class => static function (): AlertDispatcher {
            return new AlertDispatcher();
        },

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
                logger: $container->get(Logger::class),
            );
        },

        SourceAdapterInterface::class => AdapterChain::class,
        YahooFinanceAdapter::class => YahooFinanceAdapter::class,

        SemanticValidatorInterface::class => SemanticValidator::class,

        CollectionGateValidatorInterface::class => static function (Container $container): CollectionGateValidatorInterface {
            $params = Yii::$app->params;
            return new CollectionGateValidator(
                schemaValidator: $container->get(SchemaValidatorInterface::class),
                semanticValidator: $container->get(SemanticValidatorInterface::class),
                macroStalenessThresholdDays: $params['macroStalenessThresholdDays'] ?? 10,
            );
        },

        DataPointFactory::class => DataPointFactory::class,
        SourceCandidateFactory::class => SourceCandidateFactory::class,
        CompanyDataFactory::class => CompanyDataFactory::class,
        IndustryDataPackFactory::class => IndustryDataPackFactory::class,

        DataPackRepository::class => static function (): DataPackRepository {
            $params = Yii::$app->params;
            $basePath = $params['datapacksPath'] ?? '@runtime/datapacks';

            return new DataPackRepository(
                basePath: Yii::getAlias($basePath),
            );
        },

        IndustryConfigQuery::class => static function (Container $container): IndustryConfigQuery {
            return new IndustryConfigQuery(
                $container->get(SchemaValidatorInterface::class),
            );
        },

        DataPackAssembler::class => static function (Container $container): DataPackAssembler {
            return new DataPackAssembler(
                repository: $container->get(DataPackRepository::class),
            );
        },
        DataPackAssemblerInterface::class => static function (Container $container): DataPackAssemblerInterface {
            return $container->get(DataPackAssembler::class);
        },

        CollectDatapointInterface::class => static function (Container $container): CollectDatapointInterface {
            return new CollectDatapointHandler(
                webFetchClient: $container->get(WebFetchClientInterface::class),
                sourceAdapter: $container->get(SourceAdapterInterface::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: $container->get(Logger::class),
            );
        },

        CollectCompanyInterface::class => static function (Container $container): CollectCompanyInterface {
            return new CollectCompanyHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                sourceCandidateFactory: $container->get(SourceCandidateFactory::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: $container->get(Logger::class),
            );
        },

        CollectMacroInterface::class => static function (Container $container): CollectMacroInterface {
            return new CollectMacroHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                sourceCandidateFactory: $container->get(SourceCandidateFactory::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: $container->get(Logger::class),
            );
        },

        CollectIndustryInterface::class => static function (Container $container): CollectIndustryInterface {
            return new CollectIndustryHandler(
                companyCollector: $container->get(CollectCompanyInterface::class),
                macroCollector: $container->get(CollectMacroInterface::class),
                repository: $container->get(DataPackRepository::class),
                assembler: $container->get(DataPackAssemblerInterface::class),
                gateValidator: $container->get(CollectionGateValidatorInterface::class),
                alertDispatcher: $container->get(AlertDispatcher::class),
                logger: $container->get(Logger::class),
            );
        },
    ],
];
