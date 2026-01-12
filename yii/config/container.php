<?php

declare(strict_types=1);

use app\adapters\AdapterChain;
use app\adapters\BakerHughesRigCountAdapter;
use app\adapters\BlockedSourceRegistry;
use app\adapters\BloombergAdapter;
use app\adapters\EcbAdapter;
use app\adapters\EiaInventoryAdapter;
use app\adapters\FmpAdapter;
use app\adapters\LocalStorageAdapter;
use app\adapters\MorningstarAdapter;
use app\adapters\ReutersAdapter;
use app\adapters\SeekingAlphaAdapter;
use app\adapters\SourceAdapterInterface;
use app\adapters\StockAnalysisAdapter;
use app\adapters\StorageInterface;
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
use app\clients\GotenbergClient;
use app\clients\GuzzleWebFetchClient;
use app\clients\RandomUserAgentProvider;
use app\clients\RateLimiterInterface;
use app\clients\UserAgentProviderInterface;
use app\clients\WebFetchClientInterface;
use app\factories\CompanyDataFactory;
use app\factories\DataPointFactory;
use app\factories\pdf\ReportDataFactory;
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
use app\handlers\analysis\RankCompaniesHandler;
use app\handlers\analysis\RankCompaniesInterface;
use app\handlers\collection\CollectBatchHandler;
use app\handlers\collection\CollectBatchInterface;
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
use app\handlers\datasource\CreateDataSourceHandler;
use app\handlers\datasource\CreateDataSourceInterface;
use app\handlers\datasource\DeleteDataSourceHandler;
use app\handlers\datasource\DeleteDataSourceInterface;
use app\handlers\datasource\ToggleDataSourceHandler;
use app\handlers\datasource\ToggleDataSourceInterface;
use app\handlers\datasource\UpdateDataSourceHandler;
use app\handlers\datasource\UpdateDataSourceInterface;
use app\handlers\industry\AddMembersHandler;
use app\handlers\industry\AddMembersInterface;
use app\handlers\industry\CollectIndustryHandler as IndustryCollectHandler;
use app\handlers\industry\CollectIndustryInterface as IndustryCollectInterface;
use app\handlers\industry\CreateIndustryHandler;
use app\handlers\industry\CreateIndustryInterface;
use app\handlers\industry\RemoveMemberHandler;
use app\handlers\industry\RemoveMemberInterface;
use app\handlers\industry\ToggleIndustryHandler;
use app\handlers\industry\ToggleIndustryInterface;
use app\handlers\industry\UpdateIndustryHandler;
use app\handlers\industry\UpdateIndustryInterface;
use app\handlers\pdf\BundleAssembler;
use app\handlers\pdf\PdfGenerationHandler;
use app\handlers\pdf\ViewRenderer;
use app\queries\AnalysisReportReader;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\DataSourceQuery;
use app\queries\IndustryListQuery;
use app\queries\IndustryMemberQuery;
use app\queries\IndustryQuery;
use app\queries\PdfJobRepository;
use app\queries\PdfJobRepositoryInterface;
use app\queries\SectorQuery;
use app\queries\SourceBlockRepository;
use app\queries\SourceBlockRepositoryInterface;
use app\transformers\PeerAverageTransformer;
use app\validators\AnalysisGateValidator;
use app\validators\AnalysisGateValidatorInterface;
use app\validators\CollectionGateValidator;
use app\validators\CollectionGateValidatorInterface;
use app\validators\SchemaValidator;
use app\validators\SchemaValidatorInterface;
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
        GotenbergClient::class => static function (): GotenbergClient {
            return new GotenbergClient(
                new Client(),
                Yii::$app->params['gotenbergBaseUrl'] ?? 'http://aimm_gotenberg:3000',
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

        CollectionGateValidatorInterface::class => CollectionGateValidator::class,

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

        SourceBlockRepository::class => static function (): SourceBlockRepository {
            return new SourceBlockRepository(Yii::$app->db);
        },

        SourceBlockRepositoryInterface::class => static function (Container $container): SourceBlockRepositoryInterface {
            return $container->get(SourceBlockRepository::class);
        },

        CollectionRunRepository::class => static function (): CollectionRunRepository {
            return new CollectionRunRepository(Yii::$app->db);
        },

        app\queries\AnalysisReportRepository::class => static function () {
            return new app\queries\AnalysisReportRepository(Yii::$app->db);
        },

        CollectDatapointInterface::class => static function (Container $container): CollectDatapointInterface {
            return new CollectDatapointHandler(
                webFetchClient: $container->get(WebFetchClientInterface::class),
                sourceAdapter: $container->get(SourceAdapterInterface::class),
                dataPointFactory: $container->get(DataPointFactory::class),
                logger: Yii::getLogger(),
            );
        },

        CollectBatchInterface::class => static function (Container $container): CollectBatchInterface {
            return new CollectBatchHandler(
                webFetchClient: $container->get(WebFetchClientInterface::class),
                sourceAdapter: $container->get(SourceAdapterInterface::class),
                logger: Yii::getLogger(),
            );
        },

        CollectCompanyInterface::class => static function (Container $container): CollectCompanyInterface {
            return new CollectCompanyHandler(
                datapointCollector: $container->get(CollectDatapointInterface::class),
                batchCollector: $container->get(CollectBatchInterface::class),
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
                batchCollector: $container->get(CollectBatchInterface::class),
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

        // Data Source Handlers (use raw SQL to avoid Yii2 ActiveRecord bug with PHP 8.1+)
        CreateDataSourceInterface::class => static function (Container $container): CreateDataSourceInterface {
            return new CreateDataSourceHandler(
                Yii::$app->db,
                $container->get(DataSourceQuery::class),
            );
        },
        UpdateDataSourceInterface::class => static function (Container $container): UpdateDataSourceInterface {
            return new UpdateDataSourceHandler(
                Yii::$app->db,
                $container->get(DataSourceQuery::class),
            );
        },
        ToggleDataSourceInterface::class => static function (Container $container): ToggleDataSourceInterface {
            return new ToggleDataSourceHandler(
                Yii::$app->db,
                $container->get(DataSourceQuery::class),
            );
        },
        DeleteDataSourceInterface::class => static function (Container $container): DeleteDataSourceInterface {
            return new DeleteDataSourceHandler(
                Yii::$app->db,
                $container->get(DataSourceQuery::class),
            );
        },

        SetDefaultPolicyInterface::class => static function (Container $container): SetDefaultPolicyInterface {
            return new SetDefaultPolicyHandler(
                $container->get(CollectionPolicyQuery::class),
                $container->get(SectorQuery::class),
                Yii::$app->db,
                Yii::getLogger(),
            );
        },

        CreateIndustryInterface::class => static function (Container $container): CreateIndustryInterface {
            return new CreateIndustryHandler(
                $container->get(IndustryQuery::class),
                $container->get(SectorQuery::class),
                $container->get(app\queries\CompanyQuery::class),
                Yii::getLogger(),
            );
        },

        UpdateIndustryInterface::class => static function (Container $container): UpdateIndustryInterface {
            return new UpdateIndustryHandler(
                $container->get(IndustryQuery::class),
                $container->get(app\queries\CompanyQuery::class),
                Yii::getLogger(),
            );
        },

        ToggleIndustryInterface::class => static function (Container $container): ToggleIndustryInterface {
            return new ToggleIndustryHandler(
                $container->get(IndustryQuery::class),
                $container->get(app\queries\CompanyQuery::class),
                Yii::getLogger(),
            );
        },

        IndustryCollectInterface::class => static function (Container $container): IndustryCollectInterface {
            return new IndustryCollectHandler(
                $container->get(IndustryQuery::class),
                $container->get(CollectionPolicyQuery::class),
                $container->get(app\queries\CompanyQuery::class),
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
        app\queries\DataGapQuery::class => static function () {
            return new app\queries\DataGapQuery(Yii::$app->db);
        },

        // Industry and Sector Queries
        app\queries\CollectionPolicyQuery::class => static function () {
            return new app\queries\CollectionPolicyQuery(Yii::$app->db);
        },
        SectorQuery::class => static function (): SectorQuery {
            return new SectorQuery(Yii::$app->db);
        },
        IndustryQuery::class => static function (): IndustryQuery {
            return new IndustryQuery(Yii::$app->db);
        },
        IndustryListQuery::class => static function (): IndustryListQuery {
            return new IndustryListQuery(Yii::$app->db);
        },
        IndustryMemberQuery::class => static function (): IndustryMemberQuery {
            return new IndustryMemberQuery(Yii::$app->db);
        },
        DataSourceQuery::class => static function (): DataSourceQuery {
            return new DataSourceQuery(Yii::$app->db);
        },

        // Industry Member Handlers
        AddMembersInterface::class => static function (Container $container): AddMembersInterface {
            return new AddMembersHandler(
                $container->get(IndustryQuery::class),
                $container->get(app\queries\CompanyQuery::class),
                $container->get(IndustryMemberQuery::class),
                Yii::getLogger(),
            );
        },

        RemoveMemberInterface::class => static function (Container $container): RemoveMemberInterface {
            return new RemoveMemberHandler(
                $container->get(IndustryQuery::class),
                $container->get(IndustryMemberQuery::class),
                Yii::getLogger(),
            );
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

        // Industry Analysis Query and Factory
        app\factories\CompanyDataDossierFactory::class => static function (Container $container) {
            return new app\factories\CompanyDataDossierFactory(
                companyQuery: $container->get(app\queries\CompanyQuery::class),
                valuationQuery: $container->get(app\queries\ValuationSnapshotQuery::class),
                annualQuery: $container->get(app\queries\AnnualFinancialQuery::class),
                quarterlyQuery: $container->get(app\queries\QuarterlyFinancialQuery::class),
                ttmQuery: $container->get(app\queries\TtmFinancialQuery::class),
                dataPointFactory: $container->get(DataPointFactory::class),
            );
        },
        app\factories\CompanyDataDossierFactoryInterface::class => app\factories\CompanyDataDossierFactory::class,

        app\queries\IndustryAnalysisQuery::class => static function (Container $container) {
            return new app\queries\IndustryAnalysisQuery(
                companyQuery: $container->get(app\queries\CompanyQuery::class),
                companyFactory: $container->get(app\factories\CompanyDataDossierFactoryInterface::class),
                macroQuery: $container->get(app\queries\MacroIndicatorQuery::class),
                priceHistoryQuery: $container->get(app\queries\PriceHistoryQuery::class),
            );
        },

        // Analysis Handlers
        PeerAverageTransformer::class => PeerAverageTransformer::class,

        AnalysisGateValidatorInterface::class => AnalysisGateValidator::class,

        CalculateGapsInterface::class => CalculateGapsHandler::class,

        AssessFundamentalsInterface::class => AssessFundamentalsHandler::class,

        AssessRiskInterface::class => AssessRiskHandler::class,

        DetermineRatingInterface::class => DetermineRatingHandler::class,

        RankCompaniesInterface::class => RankCompaniesHandler::class,

        AnalyzeReportInterface::class => static function (Container $container): AnalyzeReportInterface {
            return new AnalyzeReportHandler(
                gateValidator: $container->get(AnalysisGateValidatorInterface::class),
                peerAverageTransformer: $container->get(PeerAverageTransformer::class),
                calculateGaps: $container->get(CalculateGapsInterface::class),
                assessFundamentals: $container->get(AssessFundamentalsInterface::class),
                assessRisk: $container->get(AssessRiskInterface::class),
                determineRating: $container->get(DetermineRatingInterface::class),
                rankCompanies: $container->get(RankCompaniesInterface::class),
            );
        },

        // PDF Generation (Phase 3)
        StorageInterface::class => static function (): StorageInterface {
            return new LocalStorageAdapter(Yii::getAlias('@runtime/pdf-storage'));
        },

        PdfJobRepository::class => static function (): PdfJobRepository {
            return new PdfJobRepository(Yii::$app->db);
        },

        PdfJobRepositoryInterface::class => static function (Container $container): PdfJobRepositoryInterface {
            return $container->get(PdfJobRepository::class);
        },

        AnalysisReportReader::class => static function (Container $container): AnalysisReportReader {
            return $container->get(app\queries\AnalysisReportRepository::class);
        },

        ReportDataFactory::class => static function (Container $container): ReportDataFactory {
            return new ReportDataFactory(
                $container->get(AnalysisReportReader::class),
            );
        },

        ViewRenderer::class => static function (): ViewRenderer {
            return new ViewRenderer(
                new yii\base\View(),
                Yii::getAlias('@app/views/report'),
            );
        },

        BundleAssembler::class => static function (): BundleAssembler {
            return new BundleAssembler(
                Yii::getAlias('@webroot/css'),
                Yii::getAlias('@webroot/fonts'),
                Yii::getAlias('@webroot/images'),
            );
        },

        PdfGenerationHandler::class => static function (Container $container): PdfGenerationHandler {
            return new PdfGenerationHandler(
                jobRepository: $container->get(PdfJobRepositoryInterface::class),
                reportDataFactory: $container->get(ReportDataFactory::class),
                viewRenderer: $container->get(ViewRenderer::class),
                bundleAssembler: $container->get(BundleAssembler::class),
                gotenbergClient: $container->get(GotenbergClient::class),
                storage: $container->get(StorageInterface::class),
                logger: Yii::getLogger(),
                db: Yii::$app->db,
            );
        },
    ],
];
