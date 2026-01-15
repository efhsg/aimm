<?php

declare(strict_types=1);

namespace tests\unit\handlers\pdf;

use app\adapters\StorageInterface;
use app\clients\GotenbergClient;
use app\dto\pdf\CompanyRankingDto;
use app\dto\pdf\GroupAveragesDto;
use app\dto\pdf\RankingMetadataDto;
use app\dto\pdf\RankingReportData;
use app\dto\pdf\RenderBundle;
use app\dto\pdf\RenderedViews;
use app\enums\PdfJobStatus;
use app\exceptions\GotenbergException;
use app\factories\pdf\ReportDataFactory;
use app\handlers\pdf\BundleAssembler;
use app\handlers\pdf\PdfGenerationHandler;
use app\handlers\pdf\ViewRenderer;
use app\models\PdfJob;
use app\queries\PdfJobRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use RuntimeException;
use yii\db\Connection;
use yii\db\Transaction;
use yii\log\Logger;

/**
 * @covers \app\handlers\pdf\PdfGenerationHandler
 */
final class PdfGenerationHandlerTest extends Unit
{
    private PdfJobRepositoryInterface $jobRepository;
    private ReportDataFactory $reportDataFactory;
    private ViewRenderer $viewRenderer;
    private BundleAssembler $bundleAssembler;
    private GotenbergClient $gotenbergClient;
    private StorageInterface $storage;
    private Logger $logger;
    private Connection $db;
    private PdfGenerationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobRepository = $this->createMock(PdfJobRepositoryInterface::class);
        $this->reportDataFactory = $this->createMock(ReportDataFactory::class);
        $this->viewRenderer = $this->createMock(ViewRenderer::class);
        $this->bundleAssembler = $this->createMock(BundleAssembler::class);
        $this->gotenbergClient = $this->createMock(GotenbergClient::class);
        $this->storage = $this->createMock(StorageInterface::class);
        $this->logger = $this->createMock(Logger::class);
        $this->db = $this->createMock(Connection::class);

        $this->handler = new PdfGenerationHandler(
            $this->jobRepository,
            $this->reportDataFactory,
            $this->viewRenderer,
            $this->bundleAssembler,
            $this->gotenbergClient,
            $this->storage,
            $this->logger,
            $this->db,
        );
    }

    public function testHandleCompletesSuccessfulJob(): void
    {
        $jobId = 42;
        $job = $this->createPendingJob($jobId);

        $this->setupSuccessfulAcquisition($jobId, $job);
        $this->setupSuccessfulPipeline($job);

        $this->storage->expects($this->once())
            ->method('store')
            ->willReturn('/storage/reports/2026/01/rpt_test_42.pdf');

        $this->jobRepository->expects($this->once())
            ->method('complete')
            ->with($jobId, '/storage/reports/2026/01/rpt_test_42.pdf');

        $this->handler->handle($jobId);
    }

    public function testHandleSkipsJobNotFound(): void
    {
        $jobId = 999;

        $this->setupTransaction();

        $this->jobRepository->method('findAndLock')
            ->with($jobId)
            ->willReturn(null);

        $this->reportDataFactory->expects($this->never())
            ->method('createRanking');

        $this->handler->handle($jobId);
    }

    public function testHandleSkipsJobNotPending(): void
    {
        $jobId = 42;
        $job = $this->createJob($jobId, PdfJobStatus::Complete->value);

        $this->setupTransaction();

        $this->jobRepository->method('findAndLock')
            ->with($jobId)
            ->willReturn($job);

        $this->reportDataFactory->expects($this->never())
            ->method('createRanking');

        $this->handler->handle($jobId);
    }

    public function testHandleFailsJobOnGotenbergError(): void
    {
        $jobId = 42;
        $job = $this->createPendingJob($jobId);

        $this->setupSuccessfulAcquisition($jobId, $job);

        $reportData = $this->createReportData();
        $renderedViews = new RenderedViews('<html>', '<header>', '<footer>');
        $bundle = $this->createBundle();

        $this->reportDataFactory->method('createRanking')->willReturn($reportData);
        $this->viewRenderer->method('renderRanking')->willReturn($renderedViews);
        $this->bundleAssembler->method('assembleRanking')->willReturn($bundle);

        $this->gotenbergClient->method('render')
            ->willThrowException(new GotenbergException('Render failed', statusCode: 500));

        $this->jobRepository->expects($this->once())
            ->method('fail')
            ->with($jobId, 'GOTENBERG_ERROR', 'Render failed');

        $this->handler->handle($jobId);
    }

    public function testHandleFailsJobOnClientError(): void
    {
        $jobId = 42;
        $job = $this->createPendingJob($jobId);

        $this->setupSuccessfulAcquisition($jobId, $job);

        $reportData = $this->createReportData();
        $renderedViews = new RenderedViews('<html>', '<header>', '<footer>');
        $bundle = $this->createBundle();

        $this->reportDataFactory->method('createRanking')->willReturn($reportData);
        $this->viewRenderer->method('renderRanking')->willReturn($renderedViews);
        $this->bundleAssembler->method('assembleRanking')->willReturn($bundle);

        $this->gotenbergClient->method('render')
            ->willThrowException(new GotenbergException('Bad request', statusCode: 400));

        $this->jobRepository->expects($this->once())
            ->method('fail')
            ->with($jobId, 'GOTENBERG_4XX', 'Bad request');

        $this->handler->handle($jobId);
    }

    public function testHandleFailsJobOnReportNotFound(): void
    {
        $jobId = 42;
        $job = $this->createPendingJob($jobId);

        $this->setupSuccessfulAcquisition($jobId, $job);

        $this->reportDataFactory->method('createRanking')
            ->willThrowException(new RuntimeException('Report not found: rpt_missing'));

        $this->jobRepository->expects($this->once())
            ->method('fail')
            ->with($jobId, 'UNKNOWN_ERROR', 'Report not found: rpt_missing');

        $this->handler->handle($jobId);
    }

    private function createPendingJob(int $id): PdfJob
    {
        return $this->createJob($id, PdfJobStatus::Pending->value);
    }

    private function createJob(int $id, string $status): PdfJob
    {
        // Create stub object with the properties we need
        return new class ($id, $status) extends PdfJob {
            public int $id;
            public string $report_id = 'rpt_test';
            public string $trace_id = 'trace_123';
            public string $status;
            public int $attempts = 0;

            public function __construct(int $id, string $status)
            {
                $this->id = $id;
                $this->status = $status;
                // Don't call parent constructor to avoid DB connection
            }

            public static function tableName(): string
            {
                return '{{%pdf_job}}';
            }
        };
    }

    private function createReportData(): RankingReportData
    {
        return new RankingReportData(
            reportId: 'rpt_test',
            traceId: 'trace_123',
            industryName: 'Test Industry',
            metadata: new RankingMetadataDto(
                companyCount: 1,
                generatedAt: '2026-01-15',
                dataAsOf: '2026-01-14',
                reportId: 'rpt_test'
            ),
            groupAverages: new GroupAveragesDto(
                fwdPe: 15.5,
                evEbitda: 10.2,
                fcfYieldPercent: 5.5,
                divYieldPercent: 2.1
            ),
            companyRankings: [
                new CompanyRankingDto(
                    rank: 1,
                    ticker: 'TEST',
                    name: 'Test Corp',
                    rating: 'buy',
                    fundamentalsAssessment: 'improving',
                    fundamentalsScore: 8.5,
                    riskAssessment: 'acceptable',
                    valuationGapPercent: -10.5,
                    valuationGapDirection: 'undervalued',
                    marketCapBillions: 100.5
                )
            ],
            generatedAt: new DateTimeImmutable(),
        );
    }

    private function createBundle(): RenderBundle
    {
        return RenderBundle::factory('trace_123')
            ->withIndexHtml('<html>')
            ->withHeaderHtml('<header>')
            ->withFooterHtml('<footer>')
            ->build();
    }

    private function setupTransaction(): void
    {
        $transaction = $this->createMock(Transaction::class);
        $this->db->method('beginTransaction')->willReturn($transaction);
    }

    private function setupSuccessfulAcquisition(int $jobId, PdfJob $job): void
    {
        $transaction = $this->createMock(Transaction::class);
        $this->db->method('beginTransaction')->willReturn($transaction);

        $this->jobRepository->method('findAndLock')
            ->with($jobId)
            ->willReturn($job);

        $this->jobRepository->method('transitionTo')
            ->willReturn(true);

        $this->jobRepository->method('findById')
            ->with($jobId)
            ->willReturn($job);
    }

    private function setupSuccessfulPipeline(PdfJob $job): void
    {
        $reportData = $this->createReportData();
        $renderedViews = new RenderedViews('<html>', '<header>', '<footer>');
        $bundle = $this->createBundle();

        $this->reportDataFactory->method('createRanking')
            ->with($job->report_id, $job->trace_id)
            ->willReturn($reportData);

        $this->viewRenderer->method('renderRanking')
            ->with($reportData)
            ->willReturn($renderedViews);

        $this->bundleAssembler->method('assembleRanking')
            ->with($renderedViews, $reportData)
            ->willReturn($bundle);

        $this->gotenbergClient->method('render')
            ->willReturn('%PDF-1.4 content');
    }
}
