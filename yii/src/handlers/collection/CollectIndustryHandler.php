<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\alerts\AlertDispatcher;
use app\dto\CollectCompanyRequest;
use app\dto\CollectIndustryRequest;
use app\dto\CollectIndustryResult;
use app\dto\CollectionLog;
use app\dto\CollectMacroRequest;
use app\dto\DataRequirements;
use app\dto\MetricDefinition;
use app\enums\CollectionStatus;
use app\exceptions\CollectionException;
use app\queries\CollectionRunRepository;
use app\queries\DataPackRepository;
use app\transformers\DataPackAssemblerInterface;
use app\validators\CollectionGateValidatorInterface;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use RuntimeException;
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
        private readonly DataPackRepository $repository,
        private readonly DataPackAssemblerInterface $assembler,
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
        $focalTicker = $this->resolveFocalTicker($request);
        $runId = $this->runRepository->create($request->config->id, $datapackId);

        $this->logger->log(
            [
                'message' => 'Starting industry collection',
                'industry' => $request->config->id,
                'datapack_id' => $datapackId,
                'company_count' => $companyCount,
                'batch_size' => $request->batchSize,
                'memory_management' => $request->enableMemoryManagement,
                'focal_ticker' => $focalTicker,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        try {
            $macroResult = $this->macroCollector->collect(
                new CollectMacroRequest(
                    requirements: $request->config->macroRequirements,
                )
            );
            $totalAttempts = count($macroResult->sourceAttempts);

            $companyStatuses = [];
            $batches = array_chunk($request->config->companies, $request->batchSize);
            $batchNumber = 0;
            $peerRequirements = $this->buildPeerRequirements($request->config->dataRequirements);

            foreach ($batches as $batch) {
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
                        $requirements = $companyConfig->ticker === $focalTicker
                            ? $request->config->dataRequirements
                            : $peerRequirements;

                        $companyResult = $this->companyCollector->collect(
                            new CollectCompanyRequest(
                                ticker: $companyConfig->ticker,
                                config: $companyConfig,
                                requirements: $requirements,
                            )
                        );

                        $this->repository->saveCompanyIntermediate(
                            $request->config->id,
                            $datapackId,
                            $companyResult->data
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
                }

                $this->runRepository->updateProgress(
                    $runId,
                    $companyCount,
                    $this->countByStatus($companyStatuses, CollectionStatus::Complete),
                    $this->countByStatus($companyStatuses, CollectionStatus::Failed)
                    + $this->countByStatus($companyStatuses, CollectionStatus::Partial)
                );

                if ($request->enableMemoryManagement) {
                    $this->manageMemory();
                }
            }

            $overallStatus = $this->determineOverallStatus(
                $companyStatuses,
                $macroResult->status,
                $companyCount
            );

            $endTime = new DateTimeImmutable();
            $collectionLog = new CollectionLog(
                startedAt: $startTime,
                completedAt: $endTime,
                durationSeconds: $endTime->getTimestamp() - $startTime->getTimestamp(),
                companyStatuses: $companyStatuses,
                macroStatus: $macroResult->status,
                totalAttempts: $totalAttempts,
            );

            $dataPackPath = $this->assembler->assemble(
                industryId: $request->config->id,
                datapackId: $datapackId,
                macro: $macroResult->data,
                collectionLog: $collectionLog,
                collectedAt: $startTime,
            );

            $dataPack = $this->repository->load($request->config->id, $datapackId);
            if ($dataPack === null) {
                throw new RuntimeException(
                    "Failed to load assembled datapack for validation: {$datapackId}"
                );
            }

            $gateResult = $this->gateValidator->validate($dataPack, $request->config, $focalTicker);
            $this->repository->saveValidation($request->config->id, $datapackId, $gateResult);
            $this->runRepository->recordErrors($runId, $gateResult);

            if (!$gateResult->passed) {
                $this->alertDispatcher->alertGateFailed(
                    $request->config->id,
                    $datapackId,
                    $gateResult->errors,
                );
                $overallStatus = CollectionStatus::Failed;
            }

            unset($dataPack);

            $this->logger->log(
                [
                    'message' => 'Industry collection complete',
                    'industry' => $request->config->id,
                    'datapack_id' => $datapackId,
                    'status' => $overallStatus->value,
                    'gate_passed' => $gateResult->passed,
                    'datapack_path' => $dataPackPath,
                    'duration_seconds' => $collectionLog->durationSeconds,
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                Logger::LEVEL_INFO,
                'collection'
            );

            $fileSizeBytes = file_exists($dataPackPath) ? (int) filesize($dataPackPath) : 0;
            $this->runRepository->complete(
                $runId,
                $overallStatus->value,
                $gateResult->passed,
                count($gateResult->errors),
                count($gateResult->warnings),
                $dataPackPath,
                $fileSizeBytes,
                $collectionLog->durationSeconds
            );

            return new CollectIndustryResult(
                industryId: $request->config->id,
                datapackId: $datapackId,
                dataPackPath: $dataPackPath,
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

    private function resolveFocalTicker(CollectIndustryRequest $request): string
    {
        $usedFallback = false;
        $focalTicker = $request->config->resolveFocalTicker($request->focalTicker, $usedFallback);

        if ($focalTicker === null) {
            return '';
        }

        if ($usedFallback) {
            $this->logger->log(
                sprintf(
                    'Focal ticker resolved to first company: %s (no explicit focal_ticker configured)',
                    $focalTicker
                ),
                Logger::LEVEL_INFO
            );
        }

        return $focalTicker;
    }

    private function buildPeerRequirements(DataRequirements $requirements): DataRequirements
    {
        return new DataRequirements(
            historyYears: $requirements->historyYears,
            quartersToFetch: $requirements->quartersToFetch,
            valuationMetrics: $this->buildPeerMetrics($requirements->valuationMetrics),
            annualFinancialMetrics: $this->buildPeerMetrics($requirements->annualFinancialMetrics),
            quarterMetrics: $this->buildPeerMetrics($requirements->quarterMetrics),
            operationalMetrics: $this->buildPeerMetrics($requirements->operationalMetrics),
        );
    }

    /**
     * Build metric definitions for peer companies.
     *
     * - Metrics with required_scope=all remain required for peers
     * - Metrics with required_scope=focal become optional for peers
     *
     * @param list<MetricDefinition> $metrics
     * @return list<MetricDefinition>
     */
    private function buildPeerMetrics(array $metrics): array
    {
        return array_map(
            static fn (MetricDefinition $metric): MetricDefinition => new MetricDefinition(
                key: $metric->key,
                unit: $metric->unit,
                required: $metric->required && $metric->requiredScope === MetricDefinition::SCOPE_ALL,
                requiredScope: $metric->requiredScope,
            ),
            $metrics,
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
