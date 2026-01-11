<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\alerts\AlertDispatcher;
use app\dto\CollectCompanyRequest;
use app\dto\CollectIndustryRequest;
use app\dto\CollectIndustryResult;
use app\dto\CollectMacroRequest;
use app\dto\GateResult;
use app\enums\CollectionStatus;
use app\exceptions\CollectionException;
use app\models\CollectionRun;
use app\queries\CollectionRunRepository;
use app\validators\CollectionGateValidatorInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use yii\log\Logger;

/**
 * Orchestrates macro and company collection for an industry.
 */
final class CollectIndustryHandler implements CollectIndustryInterface
{
    /**
     * Memory threshold (bytes) to trigger garbage collection.
     */
    private const MEMORY_THRESHOLD_BYTES = 64 * 1024 * 1024;

    public function __construct(
        private readonly CollectCompanyInterface $companyCollector,
        private readonly CollectMacroInterface $macroCollector,
        private readonly CollectionGateValidatorInterface $gateValidator,
        private readonly AlertDispatcher $alertDispatcher,
        private readonly CollectionRunRepository $runRepository,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectIndustryRequest $request): CollectIndustryResult
    {
        $datapackId = Uuid::uuid4()->toString();
        $startTime = new DateTimeImmutable();
        $companyCount = count($request->config->companies);

        if ($request->runId !== null) {
            $runId = $request->runId;
            $run = CollectionRun::findOne($runId);
            if ($run) {
                // Check if cancelled while pending
                if ($run->cancel_requested) {
                    return $this->handleCancellation(
                        $runId,
                        $request->config->industryId,
                        $datapackId,
                        [],
                        $startTime
                    );
                }
                $run->datapack_id = $datapackId;
                $run->started_at = $startTime->format('Y-m-d H:i:s');
                $run->markRunning();
            }
        } else {
            $runId = $this->runRepository->create($request->config->industryId, $datapackId);
        }

        $this->logger->log(
            [
                'message' => 'Starting industry collection',
                'industry' => $request->config->id,
                'datapack_id' => $datapackId,
                'company_count' => $companyCount,
                'batch_size' => $request->batchSize,
                'memory_management' => $request->enableMemoryManagement,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        try {
            $macroResult = $this->macroCollector->collect(
                new CollectMacroRequest(
                    requirements: $request->config->macroRequirements,
                    sourcePriorities: $request->config->sourcePriorities,
                )
            );
            $totalAttempts = count($macroResult->sourceAttempts);

            $companyStatuses = [];
            $batches = array_chunk($request->config->companies, $request->batchSize);
            $batchNumber = 0;

            foreach ($batches as $batch) {
                // Check cancellation before processing batch
                if ($this->isCancellationRequested($runId)) {
                    return $this->handleCancellation(
                        $runId,
                        $request->config->industryId,
                        $datapackId,
                        $companyStatuses,
                        $startTime
                    );
                }

                $batchNumber++;
                $this->logger->log(
                    [
                        'message' => 'Processing batch',
                        'batch' => $batchNumber,
                        'total_batches' => count($batches),
                        'companies_in_batch' => count($batch),
                        'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    ],
                    Logger::LEVEL_INFO,
                    'collection'
                );

                foreach ($batch as $companyConfig) {
                    try {
                        $companyResult = $this->companyCollector->collect(
                            new CollectCompanyRequest(
                                ticker: $companyConfig->ticker,
                                config: $companyConfig,
                                requirements: $request->config->dataRequirements,
                                sourcePriorities: $request->config->sourcePriorities,
                            )
                        );

                        $companyStatuses[$companyConfig->ticker] = $companyResult->status;
                        $totalAttempts += count($companyResult->sourceAttempts);

                        unset($companyResult);
                    } catch (CollectionException $exception) {
                        $this->logger->log(
                            [
                                'message' => 'Company collection failed',
                                'ticker' => $companyConfig->ticker,
                                'error' => $exception->getMessage(),
                            ],
                            Logger::LEVEL_ERROR,
                            'collection'
                        );

                        $companyStatuses[$companyConfig->ticker] = CollectionStatus::Failed;
                    }

                    // Update progress after each company for responsive UI
                    $this->runRepository->updateProgress(
                        $runId,
                        $companyCount,
                        $this->countByStatus($companyStatuses, CollectionStatus::Complete),
                        $this->countByStatus($companyStatuses, CollectionStatus::Failed)
                        + $this->countByStatus($companyStatuses, CollectionStatus::Partial)
                    );

                    // Check cancellation after each company
                    if ($this->isCancellationRequested($runId)) {
                        return $this->handleCancellation(
                            $runId,
                            $request->config->industryId,
                            $datapackId,
                            $companyStatuses,
                            $startTime
                        );
                    }
                }

                if ($request->enableMemoryManagement) {
                    $this->manageMemory();
                }
            }

            // Check cancellation after last batch (just in case)
            if ($this->isCancellationRequested($runId)) {
                return $this->handleCancellation(
                    $runId,
                    $request->config->industryId,
                    $datapackId,
                    $companyStatuses,
                    $startTime
                );
            }

            $overallStatus = $this->determineOverallStatus(
                $companyStatuses,
                $macroResult->status,
                $companyCount
            );

            $endTime = new DateTimeImmutable();
            $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();

            $gateResult = $this->gateValidator->validateResults(
                $companyStatuses,
                $macroResult->status,
                $request->config
            );
            $this->runRepository->recordErrors($runId, $gateResult);

            if (!$gateResult->passed) {
                $this->alertDispatcher->alertGateFailed(
                    $request->config->id,
                    $datapackId,
                    $gateResult->errors,
                );
                $overallStatus = CollectionStatus::Failed;
            }

            $this->logger->log(
                [
                    'message' => 'Industry collection complete',
                    'industry' => $request->config->id,
                    'datapack_id' => $datapackId,
                    'status' => $overallStatus->value,
                    'duration_seconds' => $durationSeconds,
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                Logger::LEVEL_INFO,
                'collection'
            );

            $this->runRepository->complete(
                $runId,
                $overallStatus->value,
                $gateResult->passed,
                count($gateResult->errors),
                count($gateResult->warnings),
                '',
                0,
                $durationSeconds
            );

            return new CollectIndustryResult(
                runId: $runId,
                industryId: (string) $request->config->id,
                datapackId: $datapackId,
                gateResult: $gateResult,
                overallStatus: $overallStatus,
                companyStatuses: $companyStatuses,
            );
        } catch (\Throwable $exception) {
            $durationSeconds = (new DateTimeImmutable())->getTimestamp() - $startTime->getTimestamp();

            $this->runRepository->complete(
                $runId,
                CollectionStatus::Failed->value,
                false,
                0,
                0,
                '',
                0,
                $durationSeconds
            );

            throw $exception;
        }
    }

    private function isCancellationRequested(int $runId): bool
    {
        return (bool) CollectionRun::find()
            ->where(['id' => $runId, 'cancel_requested' => 1])
            ->exists();
    }

    private function handleCancellation(
        int $runId,
        int $industryId,
        string $datapackId,
        array $companyStatuses,
        DateTimeImmutable $startTime
    ): CollectIndustryResult {
        $run = CollectionRun::findOne($runId);
        if ($run) {
            $run->markCancelled();
            // Calculate duration
            $durationSeconds = (new DateTimeImmutable())->getTimestamp() - $startTime->getTimestamp();
            $run->duration_seconds = $durationSeconds;
            $run->save(false, ['duration_seconds']);
        }

        $this->logger->log(
            [
                'message' => 'Industry collection cancelled by user',
                'industry' => $industryId,
                'datapack_id' => $datapackId,
                'companies_processed' => count($companyStatuses),
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return new CollectIndustryResult(
            runId: $runId,
            industryId: (string) $industryId,
            datapackId: $datapackId,
            gateResult: new GateResult(false, [], []), // Empty gate result for cancellation
            overallStatus: CollectionStatus::Cancelled,
            companyStatuses: $companyStatuses
        );
    }

    private function manageMemory(): void
    {
        $currentUsage = memory_get_usage(true);

        if ($currentUsage > self::MEMORY_THRESHOLD_BYTES) {
            gc_collect_cycles();

            $afterGc = memory_get_usage(true);
            $freedMb = round(($currentUsage - $afterGc) / 1024 / 1024, 2);

            if ($freedMb > 0) {
                $this->logger->log(
                    [
                        'message' => 'Garbage collection freed memory',
                        'freed_mb' => $freedMb,
                        'current_mb' => round($afterGc / 1024 / 1024, 2),
                    ],
                    Logger::LEVEL_INFO,
                    'collection'
                );
            }
        }
    }

    /**
     * @param array<string, CollectionStatus> $companyStatuses
     */
    private function determineOverallStatus(
        array $companyStatuses,
        CollectionStatus $macroStatus,
        int $totalCompanies
    ): CollectionStatus {
        $failedCount = count(array_filter(
            $companyStatuses,
            static fn (CollectionStatus $status): bool =>
            $status === CollectionStatus::Failed
        ));

        if ($failedCount > $totalCompanies / 2) {
            return CollectionStatus::Failed;
        }

        $hasPartialCompany = in_array(CollectionStatus::Partial, $companyStatuses, true);
        $macroPartialOrFailed = $macroStatus === CollectionStatus::Failed
            || $macroStatus === CollectionStatus::Partial;

        if ($failedCount > 0 || $hasPartialCompany || $macroPartialOrFailed) {
            return CollectionStatus::Partial;
        }

        return CollectionStatus::Complete;
    }

    /**
     * @param array<string, CollectionStatus> $companyStatuses
     */
    private function countByStatus(array $companyStatuses, CollectionStatus $status): int
    {
        return count(array_filter(
            $companyStatuses,
            static fn (CollectionStatus $value): bool => $value === $status
        ));
    }
}
