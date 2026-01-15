<?php

declare(strict_types=1);

namespace app\handlers\pdf;

use app\adapters\StorageInterface;
use app\clients\GotenbergClient;
use app\dto\pdf\PdfOptions;
use app\enums\PdfJobStatus;
use app\exceptions\GotenbergException;
use app\exceptions\PdfGenerationException;
use app\factories\pdf\ReportDataFactory;
use app\models\PdfJob;
use app\queries\PdfJobRepositoryInterface;
use Throwable;
use yii\db\Connection;
use yii\log\Logger;

/**
 * Orchestrates the PDF generation pipeline.
 *
 * Flow: Load report -> Render views -> Assemble bundle -> Call Gotenberg -> Store PDF -> Complete job
 */
final class PdfGenerationHandler
{
    public function __construct(
        private readonly PdfJobRepositoryInterface $jobRepository,
        private readonly ReportDataFactory $reportDataFactory,
        private readonly ViewRenderer $viewRenderer,
        private readonly BundleAssembler $bundleAssembler,
        private readonly GotenbergClient $gotenbergClient,
        private readonly StorageInterface $storage,
        private readonly Logger $logger,
        private readonly Connection $db,
    ) {
    }

    /**
     * Handle a PDF generation job.
     */
    public function handle(int $jobId): void
    {
        $job = $this->acquireJob($jobId);

        if ($job === null) {
            return; // Job not found or already processing
        }

        try {
            $this->process($job);
        } catch (Throwable $e) {
            $this->handleFailure($job, $e);
        }
    }

    /**
     * Acquire a job for processing with proper locking.
     */
    private function acquireJob(int $jobId): ?PdfJob
    {
        $transaction = $this->db->beginTransaction();

        try {
            $job = $this->jobRepository->findAndLock($jobId);

            if ($job === null) {
                $this->logger->log(
                    ['message' => 'Job not found', 'jobId' => $jobId],
                    Logger::LEVEL_WARNING,
                    'pdf',
                );
                $transaction->rollBack();

                return null;
            }

            if ($job->status !== PdfJobStatus::Pending->value) {
                $this->logger->log(
                    ['message' => 'Job not in pending status', 'jobId' => $jobId, 'status' => $job->status],
                    Logger::LEVEL_INFO,
                    'pdf',
                );
                $transaction->rollBack();

                return null;
            }

            $transitioned = $this->jobRepository->transitionTo(
                $jobId,
                PdfJobStatus::Pending,
                PdfJobStatus::Rendering,
            );

            if (!$transitioned) {
                $this->logger->log(
                    ['message' => 'Failed to acquire job', 'jobId' => $jobId],
                    Logger::LEVEL_INFO,
                    'pdf',
                );
                $transaction->rollBack();

                return null;
            }

            $this->jobRepository->incrementAttempts($jobId);
            $transaction->commit();

            // Refresh job to get updated state
            return $this->jobRepository->findById($jobId);
        } catch (Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    private function process(PdfJob $job): void
    {
        $jobId = (int) $job->id;
        $traceId = $job->trace_id;
        $reportId = $job->report_id;

        $this->logger->log(
            ['message' => 'Processing PDF job', 'jobId' => $jobId, 'traceId' => $traceId, 'reportId' => $reportId],
            Logger::LEVEL_INFO,
            'pdf',
        );

        // 1. Load and transform report data (ranking format with all companies)
        $reportData = $this->reportDataFactory->createRanking($reportId, $traceId);

        // 2. Render views to HTML
        $renderedViews = $this->viewRenderer->renderRanking($reportData);

        // 3. Assemble bundle
        $bundle = $this->bundleAssembler->assembleRanking($renderedViews, $reportData);

        // 4. Generate PDF via Gotenberg
        $pdfBytes = $this->gotenbergClient->render($bundle, PdfOptions::standard());

        // 5. Store PDF
        $filename = $this->generateFilename($jobId, $reportId);
        $outputUri = $this->storage->store($pdfBytes, $filename);

        // 6. Complete job
        $this->jobRepository->complete($jobId, $outputUri);

        $this->logger->log(
            [
                'message' => 'PDF job completed',
                'jobId' => $jobId,
                'traceId' => $traceId,
                'outputUri' => $outputUri,
                'pdfBytes' => strlen($pdfBytes),
            ],
            Logger::LEVEL_INFO,
            'pdf',
        );
    }

    private function handleFailure(PdfJob $job, Throwable $e): void
    {
        $jobId = (int) $job->id;

        $this->logger->log(
            [
                'message' => 'PDF generation failed',
                'jobId' => $jobId,
                'traceId' => $job->trace_id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ],
            Logger::LEVEL_ERROR,
            'pdf',
        );

        $errorCode = $this->classifyError($e);

        $this->jobRepository->fail($jobId, $errorCode, $e->getMessage());
    }

    private function classifyError(Throwable $e): string
    {
        return match (true) {
            $e instanceof PdfGenerationException => $e->errorCode,
            $e instanceof GotenbergException && $e->statusCode !== null && $e->statusCode < 500 => 'GOTENBERG_4XX',
            $e instanceof GotenbergException => 'GOTENBERG_ERROR',
            default => 'UNKNOWN_ERROR',
        };
    }

    private function generateFilename(int $jobId, string $reportId): string
    {
        $date = date('Y/m');
        $safeReportId = preg_replace('/[^a-zA-Z0-9_-]/', '', $reportId);

        return "reports/{$date}/{$safeReportId}_{$jobId}.pdf";
    }
}
