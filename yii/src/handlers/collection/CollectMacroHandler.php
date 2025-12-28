<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectDatapointRequest;
use app\dto\CollectMacroRequest;
use app\dto\CollectMacroResult;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\MacroData;
use app\dto\SourceAttempt;
use app\enums\CollectionStatus;
use app\enums\Severity;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use yii\log\Logger;

/**
 * Collects macro-level indicators for an industry.
 *
 * Gathers commodity benchmarks, margin proxies, sector indices, and
 * additional indicators using CollectDatapointHandler.
 */
final class CollectMacroHandler implements CollectMacroInterface
{
    public function __construct(
        private readonly CollectDatapointInterface $datapointCollector,
        private readonly SourceCandidateFactory $sourceCandidateFactory,
        private readonly DataPointFactory $dataPointFactory,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectMacroRequest $request): CollectMacroResult
    {
        $this->logger->log(
            [
                'message' => 'Starting macro collection',
                'commodity_benchmark' => $request->requirements->commodityBenchmark,
                'margin_proxy' => $request->requirements->marginProxy,
                'sector_index' => $request->requirements->sectorIndex,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $allAttempts = [];

        $commodityBenchmark = $this->collectBenchmark(
            $request->requirements->commodityBenchmark,
            'macro.commodity_benchmark',
            $allAttempts
        );

        $marginProxy = $this->collectBenchmark(
            $request->requirements->marginProxy,
            'macro.margin_proxy',
            $allAttempts
        );

        $sectorIndex = $this->collectIndex(
            $request->requirements->sectorIndex,
            'macro.sector_index',
            $allAttempts
        );

        $additionalIndicators = $this->collectIndicators(
            $request->requirements->requiredIndicators,
            $request->requirements->optionalIndicators,
            $allAttempts
        );

        $status = $this->determineStatus(
            $request,
            $commodityBenchmark,
            $marginProxy,
            $sectorIndex,
            $additionalIndicators
        );

        $this->logger->log(
            [
                'message' => 'Macro collection complete',
                'status' => $status->value,
                'attempts' => count($allAttempts),
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        return new CollectMacroResult(
            data: new MacroData(
                commodityBenchmark: $commodityBenchmark,
                marginProxy: $marginProxy,
                sectorIndex: $sectorIndex,
                additionalIndicators: $additionalIndicators,
            ),
            sourceAttempts: $allAttempts,
            status: $status,
        );
    }

    /**
     * @param SourceAttempt[] $allAttempts
     */
    private function collectBenchmark(
        ?string $benchmarkKey,
        string $datapointKey,
        array &$allAttempts
    ): ?DataPointMoney {
        if ($benchmarkKey === null) {
            return null;
        }

        $sources = $this->sourceCandidateFactory->forMacro($benchmarkKey);

        if (empty($sources)) {
            $this->logger->log(
                [
                    'message' => 'No sources available for benchmark',
                    'benchmark_key' => $benchmarkKey,
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );
            return null;
        }

        $result = $this->datapointCollector->collect(new CollectDatapointRequest(
            datapointKey: $datapointKey,
            sourceCandidates: $sources,
            adapterId: 'chain',
            severity: Severity::Required,
        ));

        $allAttempts = array_merge($allAttempts, $result->sourceAttempts);

        if (!$result->found) {
            return null;
        }

        $datapoint = $result->datapoint;
        if (!$datapoint instanceof DataPointMoney) {
            return null;
        }

        return $datapoint->value !== null ? $datapoint : null;
    }

    /**
     * @param SourceAttempt[] $allAttempts
     */
    private function collectIndex(
        ?string $indexKey,
        string $datapointKey,
        array &$allAttempts
    ): ?DataPointNumber {
        if ($indexKey === null) {
            return null;
        }

        $sources = $this->sourceCandidateFactory->forMacro($indexKey);

        if (empty($sources)) {
            $this->logger->log(
                [
                    'message' => 'No sources available for index',
                    'index_key' => $indexKey,
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );
            return null;
        }

        $result = $this->datapointCollector->collect(new CollectDatapointRequest(
            datapointKey: $datapointKey,
            sourceCandidates: $sources,
            adapterId: 'chain',
            severity: Severity::Optional,
        ));

        $allAttempts = array_merge($allAttempts, $result->sourceAttempts);

        if (!$result->found) {
            return null;
        }

        $datapoint = $result->datapoint;
        if (!$datapoint instanceof DataPointNumber) {
            return null;
        }

        return $datapoint->value !== null ? $datapoint : null;
    }

    /**
     * @param list<string> $requiredIndicators
     * @param list<string> $optionalIndicators
     * @param SourceAttempt[] $allAttempts
     * @return array<string, DataPointMoney|DataPointNumber>
     */
    private function collectIndicators(
        array $requiredIndicators,
        array $optionalIndicators,
        array &$allAttempts
    ): array {
        $indicators = [];

        foreach ($requiredIndicators as $indicatorKey) {
            $datapoint = $this->collectIndicator($indicatorKey, Severity::Required, $allAttempts);
            if ($datapoint !== null) {
                $indicators[$indicatorKey] = $datapoint;
            }
        }

        foreach ($optionalIndicators as $indicatorKey) {
            $datapoint = $this->collectIndicator($indicatorKey, Severity::Optional, $allAttempts);
            if ($datapoint !== null) {
                $indicators[$indicatorKey] = $datapoint;
            }
        }

        return $indicators;
    }

    /**
     * @param SourceAttempt[] $allAttempts
     */
    private function collectIndicator(
        string $indicatorKey,
        Severity $severity,
        array &$allAttempts
    ): DataPointMoney|DataPointNumber|null {
        $sources = $this->sourceCandidateFactory->forMacro($indicatorKey);

        if (empty($sources)) {
            $this->logger->log(
                [
                    'message' => 'No sources available for indicator',
                    'indicator_key' => $indicatorKey,
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );
            return null;
        }

        $result = $this->datapointCollector->collect(new CollectDatapointRequest(
            datapointKey: $indicatorKey,
            sourceCandidates: $sources,
            adapterId: 'chain',
            severity: $severity,
        ));

        $allAttempts = array_merge($allAttempts, $result->sourceAttempts);

        if (!$result->found) {
            return null;
        }

        $datapoint = $result->datapoint;
        if ($datapoint instanceof DataPointMoney && $datapoint->value !== null) {
            return $datapoint;
        }
        if ($datapoint instanceof DataPointNumber && $datapoint->value !== null) {
            return $datapoint;
        }

        return null;
    }

    /**
     * @param array<string, DataPointMoney|DataPointNumber> $additionalIndicators
     */
    private function determineStatus(
        CollectMacroRequest $request,
        ?DataPointMoney $commodityBenchmark,
        ?DataPointMoney $marginProxy,
        ?DataPointNumber $sectorIndex,
        array $additionalIndicators
    ): CollectionStatus {
        $missingRequired = 0;
        $missingOptional = 0;
        $hasRequiredConfigured = $request->requirements->commodityBenchmark !== null
            || $request->requirements->marginProxy !== null
            || $request->requirements->requiredIndicators !== [];

        if ($request->requirements->commodityBenchmark !== null && $commodityBenchmark === null) {
            $missingRequired++;
        }

        if ($request->requirements->marginProxy !== null && $marginProxy === null) {
            $missingRequired++;
        }

        if ($request->requirements->sectorIndex !== null && $sectorIndex === null) {
            $missingOptional++;
        }

        foreach ($request->requirements->requiredIndicators as $indicatorKey) {
            if (!isset($additionalIndicators[$indicatorKey])) {
                $missingRequired++;
            }
        }

        foreach ($request->requirements->optionalIndicators as $indicatorKey) {
            if (!isset($additionalIndicators[$indicatorKey])) {
                $missingOptional++;
            }
        }

        if ($missingRequired > 0) {
            $hasSomeData = $commodityBenchmark !== null
                || $marginProxy !== null
                || $sectorIndex !== null
                || !empty($additionalIndicators);

            return $hasSomeData ? CollectionStatus::Partial : CollectionStatus::Failed;
        }

        if ($missingOptional > 0 && $hasRequiredConfigured) {
            return CollectionStatus::Partial;
        }

        return CollectionStatus::Complete;
    }
}
