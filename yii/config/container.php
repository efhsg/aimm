<?php

declare(strict_types=1);

use app\adapters\AdapterChain;
use app\adapters\BakerHughesRigCountAdapter;
use app\adapters\BlockedSourceRegistry;
use app\adapters\BloombergAdapter;
use app\adapters\CachedDataAdapter;
use app\adapters\EiaInventoryAdapter;
use app\adapters\MorningstarAdapter;
use app\adapters\ReutersAdapter;
use app\adapters\SeekingAlphaAdapter;
use app\adapters\SourceAdapterInterface;
use app\adapters\StockAnalysisAdapter;
use app\adapters\WsjAdapter;
use app\adapters\YahooFinanceAdapter;
use app\alerts\AlertDispatcher;
use app\alerts\EmailAlertNotifier;
use app\alerts\SlackAlertNotifier;
use app\clients\AllowedDomainPolicy;
use app\clients\AllowedDomainPolicyInterface;
use app\clients\BlockDetector;
use app\clients\BlockDetectorInterface;
use app\clients\DatabaseRateLimiter;
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
use app\handlers\industryconfig\CreateIndustryConfigHandler;
use app\handlers\industryconfig\CreateIndustryConfigInterface;
use app\handlers\industryconfig\ToggleIndustryConfigHandler;
use app\handlers\industryconfig\ToggleIndustryConfigInterface;
use app\handlers\industryconfig\UpdateIndustryConfigHandler;
use app\handlers\industryconfig\UpdateIndustryConfigInterface;
use app\queries\CollectionRunRepository;
use app\queries\DataPackRepository;
use app\queries\IndustryConfigListQuery;
use app\queries\IndustryConfigQuery;
use app\queries\SourceBlockRepository;
use app\queries\SourceBlockRepositoryInterface;
use app\transformers\DataPackAssembler;
use app\transformers\DataPackAssemblerInterface;
use app\validators\CollectionGateValidator;
use app\validators\CollectionGateValidatorInterface;
use app\validators\SchemaValidator;
use app\validators\SchemaValidatorInterface;
use app\validators\SemanticValidator;
use app\validators\SemanticValidatorInterface;
use GuzzleHttp\Client;
use yii\di\Container;

return [
    'singletons' => [
        RateLimiterInterface::class => static function (Container $container): RateLimiterInterface {
            $type = Yii::$app->params['rateLimiter'] ?? 'file';

            if ($type === 'database') {
                return new DatabaseRateLimiter(
                    $container->get(SourceBlockRepository::class),
                );
            }

            return new FileRateLimiter(Yii::getAlias('@runtime/ratelimit'));
        },
        BlockDetectorInterface::class => BlockDetector::class,
        AllowedDomainPolicyInterface::class => AllowedDomainPolicy::class,
        SchemaValidatorInterface::class => static function (): SchemaValidatorInterface {
            return new SchemaValidator(Yii::$app->basePath . '/config/schemas');
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
                logger: Yii::getLogger(),
            );
        },

        AlertDispatcher::class => static function (): AlertDispatcher {
            $notifiers = [];

            $slackWebhook = Yii::$app->params['alerts']['slack_webhook'] ?? null;
            if (is_string($slackWebhook) && $slackWebhook !== '') {
                $notifiers[] = new SlackAlertNotifier(
                    webhookUrl: $slackWebhook,
                    httpClient: new Client(),
                );
            }

            $alertEmail = Yii::$app->params['alerts']['email'] ?? null;
            if (is_string($alertEmail) && $alertEmail !== '' && Yii::$app->has('mailer')) {
                $notifiers[] = new EmailAlertNotifier(
                    mailer: Yii::$app->mailer,
                    recipientEmail: $alertEmail,
                    fromEmail: Yii::$app->params['alerts']['from_email'] ?? 'noreply@aimm.dev',
                );
            }

            return new AlertDispatcher($notifiers);
        },

        BlockedSourceRegistry::class => BlockedSourceRegistry::class,

        CachedDataAdapter::class => static function (Container $container): CachedDataAdapter {
            $industry = null;
            if (Yii::$app->request instanceof \yii\web\Request) {
                $industry = Yii::$app->request->getParam('industry');
            }
            if (!is_string($industry) || $industry === '') {
                $paramIndustry = Yii::$app->params['collectionIndustryId'] ?? null;
                if (is_string($paramIndustry) && $paramIndustry !== '') {
                    $industry = $paramIndustry;
                }
            }
            if (!is_string($industry) || $industry === '') {
                $industry = 'unknown';
            }
            return new CachedDataAdapter(
                $container->get(DataPackRepository::class),
                $industry,
            );
        },

        AdapterChain::class => static function (Container $container): AdapterChain {
            return new AdapterChain(
                adapters: [
                    $container->get(YahooFinanceAdapter::class),
                    $container->get(BakerHughesRigCountAdapter::class),
                    $container->get(EiaInventoryAdapter::class),
                    $container->get(StockAnalysisAdapter::class),
                    $container->get(ReutersAdapter::class),
                    $container->get(WsjAdapter::class),
                    $container->get(BloombergAdapter::class),
                    $container->get(MorningstarAdapter::class),
                    $container->get(SeekingAlphaAdapter::class),
                    $container->get(CachedDataAdapter::class),
                ],
                blockedRegistry: $container->get(BlockedSourceRegistry::class),
                logger: Yii::getLogger(),
            );
        },

        SourceAdapterInterface::class => AdapterChain::class,
        YahooFinanceAdapter::class => YahooFinanceAdapter::class,
        BakerHughesRigCountAdapter::class => BakerHughesRigCountAdapter::class,
        EiaInventoryAdapter::class => EiaInventoryAdapter::class,
        StockAnalysisAdapter::class => StockAnalysisAdapter::class,
        ReutersAdapter::class => ReutersAdapter::class,
        WsjAdapter::class => WsjAdapter::class,
        BloombergAdapter::class => BloombergAdapter::class,
        MorningstarAdapter::class => MorningstarAdapter::class,
        SeekingAlphaAdapter::class => SeekingAlphaAdapter::class,

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
        SourceCandidateFactory::class => static function (): SourceCandidateFactory {
            $params = Yii::$app->params;
            return new SourceCandidateFactory(
                rigCountXlsxUrl: $params['rigCountXlsxUrl'] ?? null,
                eiaApiKey: $params['eiaApiKey'] ?? null,
                eiaInventorySeriesId: $params['eiaInventorySeriesId'] ?? null,
            );
        },
        CompanyDataFactory::class => CompanyDataFactory::class,
        IndustryDataPackFactory::class => IndustryDataPackFactory::class,

        SourceBlockRepository::class => static function (): SourceBlockRepository {
            return new SourceBlockRepository(Yii::$app->db);
        },

        SourceBlockRepositoryInterface::class => static function (Container $container): SourceBlockRepositoryInterface {
            return $container->get(SourceBlockRepository::class);
        },

        CollectionRunRepository::class => static function (): CollectionRunRepository {
            return new CollectionRunRepository(Yii::$app->db);
        },

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

        IndustryConfigListQuery::class => static function (Container $container): IndustryConfigListQuery {
            return new IndustryConfigListQuery(
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
                logger: Yii::getLogger(),
            );
        },

        CollectCompanyInterface::class => static function (Container $container): CollectCompanyInterface {
            return new CollectCompanyHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                sourceCandidateFactory: $container->get(SourceCandidateFactory::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: Yii::getLogger(),
            );
        },

        CollectMacroInterface::class => static function (Container $container): CollectMacroInterface {
            return new CollectMacroHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                sourceCandidateFactory: $container->get(SourceCandidateFactory::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: Yii::getLogger(),
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
                runRepository: $container->get(CollectionRunRepository::class),
                logger: Yii::getLogger(),
            );
        },

        CreateIndustryConfigInterface::class => static function (): CreateIndustryConfigInterface {
            return new CreateIndustryConfigHandler(
                logger: Yii::getLogger(),
            );
        },

        UpdateIndustryConfigInterface::class => static function (): UpdateIndustryConfigInterface {
            return new UpdateIndustryConfigHandler(
                logger: Yii::getLogger(),
            );
        },

        ToggleIndustryConfigInterface::class => static function (): ToggleIndustryConfigInterface {
            return new ToggleIndustryConfigHandler(
                logger: Yii::getLogger(),
            );
        },
    ],
];
