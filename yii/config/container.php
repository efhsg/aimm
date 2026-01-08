<?php

declare(strict_types=1);

use app\adapters\AdapterChain;
use app\adapters\BakerHughesRigCountAdapter;
use app\adapters\BlockedSourceRegistry;
use app\adapters\BloombergAdapter;
use app\adapters\EcbAdapter;
use app\adapters\EiaInventoryAdapter;
use app\adapters\FmpAdapter;
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
use app\handlers\analysis\AnalyzeReportHandler;
use app\handlers\analysis\AnalyzeReportInterface;
use app\handlers\analysis\AssessFundamentalsHandler;
use app\handlers\analysis\AssessFundamentalsInterface;
use app\handlers\analysis\AssessRiskHandler;
use app\handlers\analysis\AssessRiskInterface;
use app\handlers\analysis\CalculateGapsHandler;
use app\handlers\analysis\CalculateGapsInterface;
use app\handlers\analysis\DetermineRatingHandler;
use app\handlers\analysis\DetermineRatingInterface;
use app\handlers\collection\CollectCompanyHandler;
use app\handlers\collection\CollectCompanyInterface;
use app\handlers\collection\CollectDatapointHandler;
use app\handlers\collection\CollectDatapointInterface;
use app\handlers\collection\CollectIndustryHandler;
use app\handlers\collection\CollectIndustryInterface;
use app\handlers\collection\CollectMacroHandler;
use app\handlers\collection\CollectMacroInterface;
use app\handlers\collectionpolicy\CreateCollectionPolicyHandler;
use app\handlers\collectionpolicy\CreateCollectionPolicyInterface;
use app\handlers\collectionpolicy\DeleteCollectionPolicyHandler;
use app\handlers\collectionpolicy\DeleteCollectionPolicyInterface;
use app\handlers\collectionpolicy\SetDefaultPolicyHandler;
use app\handlers\collectionpolicy\SetDefaultPolicyInterface;
use app\handlers\collectionpolicy\UpdateCollectionPolicyHandler;
use app\handlers\collectionpolicy\UpdateCollectionPolicyInterface;
use app\handlers\peergroup\AddFocalHandler;
use app\handlers\peergroup\AddFocalInterface;
use app\handlers\peergroup\AddMembersHandler;
use app\handlers\peergroup\AddMembersInterface;
use app\handlers\peergroup\ClearFocalsHandler;
use app\handlers\peergroup\ClearFocalsInterface;
use app\handlers\peergroup\CollectPeerGroupHandler;
use app\handlers\peergroup\CollectPeerGroupInterface;
use app\handlers\peergroup\CreatePeerGroupHandler;
use app\handlers\peergroup\CreatePeerGroupInterface;
use app\handlers\peergroup\RemoveFocalHandler;
use app\handlers\peergroup\RemoveFocalInterface;
use app\handlers\peergroup\RemoveMemberHandler;
use app\handlers\peergroup\RemoveMemberInterface;
use app\handlers\peergroup\SetFocalHandler;
use app\handlers\peergroup\SetFocalInterface;
use app\handlers\peergroup\TogglePeerGroupHandler;
use app\handlers\peergroup\TogglePeerGroupInterface;
use app\handlers\peergroup\UpdatePeerGroupHandler;
use app\handlers\peergroup\UpdatePeerGroupInterface;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\PeerGroupListQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use app\queries\SourceBlockRepository;
use app\queries\SourceBlockRepositoryInterface;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidator;
use app\validators\AnalysisGateValidatorInterface;
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

        AdapterChain::class => static function (Container $container): AdapterChain {
            return new AdapterChain(
                adapters: [
                    $container->get(FmpAdapter::class),
                    $container->get(YahooFinanceAdapter::class),
                    $container->get(BakerHughesRigCountAdapter::class),
                    $container->get(EiaInventoryAdapter::class),
                    $container->get(EcbAdapter::class),
                    $container->get(StockAnalysisAdapter::class),
                    $container->get(ReutersAdapter::class),
                    $container->get(WsjAdapter::class),
                    $container->get(BloombergAdapter::class),
                    $container->get(MorningstarAdapter::class),
                    $container->get(SeekingAlphaAdapter::class),
                ],
                blockedRegistry: $container->get(BlockedSourceRegistry::class),
                logger: Yii::getLogger(),
            );
        },

        SourceAdapterInterface::class => AdapterChain::class,
        FmpAdapter::class => FmpAdapter::class,
        YahooFinanceAdapter::class => YahooFinanceAdapter::class,
        BakerHughesRigCountAdapter::class => BakerHughesRigCountAdapter::class,
        EiaInventoryAdapter::class => EiaInventoryAdapter::class,
        EcbAdapter::class => EcbAdapter::class,
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
                fmpApiKey: $params['fmpApiKey'] ?? null,
                logger: Yii::getLogger(),
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
                companyQuery: $container->get(app\queries\CompanyQuery::class),
                annualQuery: $container->get(app\queries\AnnualFinancialQuery::class),
                quarterlyQuery: $container->get(app\queries\QuarterlyFinancialQuery::class),
                valuationQuery: $container->get(app\queries\ValuationSnapshotQuery::class),
            );
        },

        CollectMacroInterface::class => static function (Container $container): CollectMacroInterface {
            return new CollectMacroHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                sourceCandidateFactory: $container->get(SourceCandidateFactory::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: Yii::getLogger(),
                macroQuery: $container->get(app\queries\MacroIndicatorQuery::class),
                priceQuery: $container->get(app\queries\PriceHistoryQuery::class),
            );
        },

        CollectIndustryInterface::class => static function (Container $container): CollectIndustryInterface {
            return new CollectIndustryHandler(
                companyCollector: $container->get(CollectCompanyInterface::class),
                macroCollector: $container->get(CollectMacroInterface::class),
                gateValidator: $container->get(CollectionGateValidatorInterface::class),
                alertDispatcher: $container->get(AlertDispatcher::class),
                runRepository: $container->get(CollectionRunRepository::class),
                logger: Yii::getLogger(),
            );
        },

        CreateCollectionPolicyInterface::class => static function (Container $container): CreateCollectionPolicyInterface {
            return new CreateCollectionPolicyHandler(
                $container->get(CollectionPolicyQuery::class),
                Yii::getLogger(),
            );
        },

        UpdateCollectionPolicyInterface::class => static function (Container $container): UpdateCollectionPolicyInterface {
            return new UpdateCollectionPolicyHandler(
                $container->get(CollectionPolicyQuery::class),
                Yii::getLogger(),
            );
        },

        DeleteCollectionPolicyInterface::class => static function (Container $container): DeleteCollectionPolicyInterface {
            return new DeleteCollectionPolicyHandler(
                $container->get(CollectionPolicyQuery::class),
                Yii::$app->db,
                Yii::getLogger(),
            );
        },

        SetDefaultPolicyInterface::class => static function (Container $container): SetDefaultPolicyInterface {
            return new SetDefaultPolicyHandler(
                $container->get(CollectionPolicyQuery::class),
                Yii::getLogger(),
            );
        },

        CreatePeerGroupInterface::class => static function (Container $container): CreatePeerGroupInterface {
            return new CreatePeerGroupHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        UpdatePeerGroupInterface::class => static function (Container $container): UpdatePeerGroupInterface {
            return new UpdatePeerGroupHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        TogglePeerGroupInterface::class => static function (Container $container): TogglePeerGroupInterface {
            return new TogglePeerGroupHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        AddMembersInterface::class => static function (Container $container): AddMembersInterface {
            return new AddMembersHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                $container->get(CompanyQuery::class),
                Yii::getLogger(),
            );
        },

        RemoveMemberInterface::class => static function (Container $container): RemoveMemberInterface {
            return new RemoveMemberHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        SetFocalInterface::class => static function (Container $container): SetFocalInterface {
            return new SetFocalHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        AddFocalInterface::class => static function (Container $container): AddFocalInterface {
            return new AddFocalHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        RemoveFocalInterface::class => static function (Container $container): RemoveFocalInterface {
            return new RemoveFocalHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        ClearFocalsInterface::class => static function (Container $container): ClearFocalsInterface {
            return new ClearFocalsHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                Yii::getLogger(),
            );
        },

        CollectPeerGroupInterface::class => static function (Container $container): CollectPeerGroupInterface {
            return new CollectPeerGroupHandler(
                $container->get(PeerGroupQuery::class),
                $container->get(PeerGroupMemberQuery::class),
                $container->get(CollectionPolicyQuery::class),
                $container->get(CompanyQuery::class),
                $container->get(CollectIndustryInterface::class),
                $container->get(CollectionRunRepository::class),
                Yii::getLogger(),
            );
        },

        // Dossier Queries
        app\queries\CompanyQuery::class => static function () {
            return new app\queries\CompanyQuery(Yii::$app->db);
        },
        app\queries\AnnualFinancialQuery::class => static function () {
            return new app\queries\AnnualFinancialQuery(Yii::$app->db);
        },
        app\queries\QuarterlyFinancialQuery::class => static function () {
            return new app\queries\QuarterlyFinancialQuery(Yii::$app->db);
        },
        app\queries\TtmFinancialQuery::class => static function () {
            return new app\queries\TtmFinancialQuery(Yii::$app->db);
        },
        app\queries\ValuationSnapshotQuery::class => static function () {
            return new app\queries\ValuationSnapshotQuery(Yii::$app->db);
        },
        app\queries\FxRateQuery::class => static function () {
            return new app\queries\FxRateQuery(Yii::$app->db);
        },
        app\queries\PriceHistoryQuery::class => static function () {
            return new app\queries\PriceHistoryQuery(Yii::$app->db);
        },
        app\queries\MacroIndicatorQuery::class => static function () {
            return new app\queries\MacroIndicatorQuery(Yii::$app->db);
        },
        app\queries\CollectionAttemptQuery::class => static function () {
            return new app\queries\CollectionAttemptQuery(Yii::$app->db);
        },
        app\queries\DataGapQuery::class => static function () {
            return new app\queries\DataGapQuery(Yii::$app->db);
        },

        // Peer Group Queries
        app\queries\CollectionPolicyQuery::class => static function () {
            return new app\queries\CollectionPolicyQuery(Yii::$app->db);
        },
        app\queries\PeerGroupQuery::class => static function () {
            return new app\queries\PeerGroupQuery(Yii::$app->db);
        },
        app\queries\PeerGroupMemberQuery::class => static function () {
            return new app\queries\PeerGroupMemberQuery(Yii::$app->db);
        },
        PeerGroupListQuery::class => static function (): PeerGroupListQuery {
            return new PeerGroupListQuery(Yii::$app->db);
        },

        // Dossier Transformers
        app\transformers\CurrencyConverter::class => static function (Container $container) {
            return new app\transformers\CurrencyConverter(
                $container->get(app\queries\FxRateQuery::class)
            );
        },

        // Dossier Handlers
        app\handlers\dossier\TtmCalculator::class => static function (Container $container) {
            return new app\handlers\dossier\TtmCalculator(
                $container->get(app\queries\QuarterlyFinancialQuery::class),
                $container->get(app\queries\TtmFinancialQuery::class)
            );
        },
        app\handlers\dossier\RecalculateTtmOnQuarterlyCollected::class => static function (Container $container) {
            return new app\handlers\dossier\RecalculateTtmOnQuarterlyCollected(
                $container->get(app\handlers\dossier\TtmCalculator::class)
            );
        },

        // Analysis Handlers
        PeerAverageTransformer::class => PeerAverageTransformer::class,

        AnalysisGateValidatorInterface::class => AnalysisGateValidator::class,

        CalculateGapsInterface::class => CalculateGapsHandler::class,

        AssessFundamentalsInterface::class => AssessFundamentalsHandler::class,

        AssessRiskInterface::class => AssessRiskHandler::class,

        DetermineRatingInterface::class => DetermineRatingHandler::class,

        AnalyzeReportInterface::class => static function (Container $container): AnalyzeReportInterface {
            return new AnalyzeReportHandler(
                gateValidator: $container->get(AnalysisGateValidatorInterface::class),
                peerAverageTransformer: $container->get(PeerAverageTransformer::class),
                calculateGaps: $container->get(CalculateGapsInterface::class),
                assessFundamentals: $container->get(AssessFundamentalsInterface::class),
                assessRisk: $container->get(AssessRiskInterface::class),
                determineRating: $container->get(DetermineRatingInterface::class),
            );
        },
    ],
];
