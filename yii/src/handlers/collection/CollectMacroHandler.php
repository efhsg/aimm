<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectBatchRequest;
use app\dto\CollectDatapointRequest;
use app\dto\CollectMacroRequest;
use app\dto\CollectMacroResult;
use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\MacroData;
use app\dto\SourceAttempt;
use app\dto\SourcePriorities;
use app\enums\CollectionStatus;
use app\enums\Severity;
use app\factories\DataPointFactory;
use app\factories\SourceCandidateFactory;
use app\queries\MacroIndicatorQuery;
use app\queries\PriceHistoryQuery;
use DateTimeImmutable;
use Yii;
use yii\log\Logger;

/**
 * Collects macro-level indicators for an industry.
 *
 * Gathers commodity benchmarks, margin proxies, sector indices, and
 * additional indicators using CollectDatapointHandler.
 *
 * Writes to Company Dossier (macro_indicator, price_history).
 */
final class CollectMacroHandler implements CollectMacroInterface
{
    public function __construct(
        private readonly CollectDatapointInterface $datapointCollector,
        private readonly CollectBatchInterface $batchCollector,
        private readonly SourceCandidateFactory $sourceCandidateFactory,
        private readonly DataPointFactory $dataPointFactory,
        private readonly Logger $logger,
        private readonly MacroIndicatorQuery $macroQuery,
        private readonly PriceHistoryQuery $priceQuery,
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
            $allAttempts,
            $request->sourcePriorities
        );

        $marginProxy = $this->collectBenchmark(
            $request->requirements->marginProxy,
            'macro.margin_proxy',
            $allAttempts,
            $request->sourcePriorities
        );

        $sectorIndex = $this->collectIndex(
            $request->requirements->sectorIndex,
            'macro.sector_index',
            $allAttempts,
            $request->sourcePriorities
        );

        $additionalIndicators = $this->collectIndicators(
            $request->requirements->requiredIndicators,
            $request->requirements->optionalIndicators,
            $allAttempts,
            $request->sourcePriorities
        );

        // Save to Dossier
        $this->saveToDossier(
            $request,
            $commodityBenchmark,
            $marginProxy,
            $sectorIndex,
            $additionalIndicators
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

    private function saveToDossier(
        CollectMacroRequest $request,
        ?DataPointMoney $commodity,
        ?DataPointMoney $margin,
        ?DataPointNumber $index,
        array $indicators
    ): void {
        $now = new DateTimeImmutable();
        $dateStr = $now->format('Y-m-d');
        $transaction = Yii::$app->db->beginTransaction();

        try {
            // Commodity -> PriceHistory
            if ($commodity !== null && $commodity->value !== null && $request->requirements->commodityBenchmark) {
                $exists = $this->priceQuery->findBySymbolAndDate($request->requirements->commodityBenchmark, $now);
                if (!$exists) {
                    $this->priceQuery->insert([
                        'symbol' => $request->requirements->commodityBenchmark,
                        'symbol_type' => 'commodity',
                        'price_date' => $dateStr,
                        'close' => $commodity->value,
                        'currency' => $commodity->currency,
                        'source_adapter' => 'web_fetch',
                        'collected_at' => $now->format('Y-m-d H:i:s'),
                        'provider_id' => $commodity->providerId,
                    ]);
                }
            }

            // Margin Proxy -> PriceHistory (commodity type)
            if ($margin !== null && $margin->value !== null && $request->requirements->marginProxy) {
                $exists = $this->priceQuery->findBySymbolAndDate($request->requirements->marginProxy, $now);
                if (!$exists) {
                    $this->priceQuery->insert([
                        'symbol' => $request->requirements->marginProxy,
                        'symbol_type' => 'commodity',
                        'price_date' => $dateStr,
                        'close' => $margin->value,
                        'currency' => $margin->currency,
                        'source_adapter' => 'web_fetch',
                        'collected_at' => $now->format('Y-m-d H:i:s'),
                        'provider_id' => $margin->providerId,
                    ]);
                }
            }

            // Sector Index -> PriceHistory
            // Note: Indices are measured in points, not currency. Using 'XXX' (ISO no-currency code).
            if ($index !== null && $index->value !== null && $request->requirements->sectorIndex) {
                $exists = $this->priceQuery->findBySymbolAndDate($request->requirements->sectorIndex, $now);
                if (!$exists) {
                    $this->priceQuery->insert([
                        'symbol' => $request->requirements->sectorIndex,
                        'symbol_type' => 'index',
                        'price_date' => $dateStr,
                        'close' => $index->value,
                        'currency' => 'XXX',
                        'source_adapter' => 'web_fetch',
                        'collected_at' => $now->format('Y-m-d H:i:s'),
                        'provider_id' => $index->providerId,
                    ]);
                }
            }

            // Indicators -> MacroIndicator
            foreach ($indicators as $key => $datapoint) {
                if ($datapoint->value !== null) {
                    $exists = $this->macroQuery->findByKeyAndDate($key, $now);
                    if (!$exists) {
                        $unit = 'count';
                        if ($datapoint instanceof DataPointMoney) {
                            $unit = $datapoint->currency;
                        } elseif ($datapoint instanceof DataPointNumber) {
                            $unit = 'number';
                        }

                        $this->macroQuery->insert([
                            'indicator_key' => $key,
                            'indicator_date' => $dateStr,
                            'value' => $datapoint->value,
                            'unit' => $unit,
                            'source_adapter' => 'web_fetch',
                            'source_url' => $datapoint->sourceUrl,
                            'collected_at' => $now->format('Y-m-d H:i:s'),
                            'provider_id' => $datapoint->providerId,
                        ]);
                    }
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->logger->log(
                ['message' => 'Failed to save macro data', 'error' => $e->getMessage()],
                Logger::LEVEL_ERROR,
                'collection'
            );
            throw $e;
        }
    }

    /**
     * @param SourceAttempt[] $allAttempts
     */
    private function collectBenchmark(
        ?string $benchmarkKey,
        string $datapointKey,
        array &$allAttempts,
        ?SourcePriorities $priorities = null
    ): ?DataPointMoney {
        if ($benchmarkKey === null) {
            return null;
        }

        $sources = $this->sourceCandidateFactory->forMacro($benchmarkKey, $priorities, 'benchmarks');

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
        array &$allAttempts,
        ?SourcePriorities $priorities = null
    ): ?DataPointNumber {
        if ($indexKey === null) {
            return null;
        }

        $sources = $this->sourceCandidateFactory->forMacro($indexKey, $priorities, 'benchmarks');

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
        array &$allAttempts,
        ?SourcePriorities $priorities = null
    ): array {
        $allIndicatorKeys = array_merge($requiredIndicators, $optionalIndicators);

        if ($allIndicatorKeys === []) {
            return [];
        }

        // Get sources for all indicators - aggregates unique sources across all keys
        $sources = $this->sourceCandidateFactory->forMacroIndicators($allIndicatorKeys, $priorities, 'macro');

        if (empty($sources)) {
            $this->logger->log(
                [
                    'message' => 'No sources available for indicators batch',
                    'indicator_count' => count($allIndicatorKeys),
                ],
                Logger::LEVEL_WARNING,
                'collection'
            );
            return [];
        }

        $this->logger->log(
            [
                'message' => 'Starting batch indicators collection',
                'all_keys' => count($allIndicatorKeys),
                'required_keys' => count($requiredIndicators),
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        $batchResult = $this->batchCollector->collect(new CollectBatchRequest(
            datapointKeys: $allIndicatorKeys,
            requiredKeys: $requiredIndicators,
            sourceCandidates: $sources,
        ));

        $allAttempts = array_merge($allAttempts, $batchResult->sourceAttempts);

        $this->logger->log(
            [
                'message' => 'Batch indicators collection complete',
                'found' => count($batchResult->found),
                'not_found' => count($batchResult->notFound),
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        // Convert extractions to datapoints
        $indicators = [];
        foreach ($batchResult->found as $indicatorKey => $extraction) {
            $datapoint = $this->dataPointFactory->fromBatchExtraction($extraction);
            if ($datapoint instanceof DataPointMoney && $datapoint->value !== null) {
                $indicators[$indicatorKey] = $datapoint;
            } elseif ($datapoint instanceof DataPointNumber && $datapoint->value !== null) {
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
        array &$allAttempts,
        ?SourcePriorities $priorities = null
    ): DataPointMoney|DataPointNumber|null {
        $sources = $this->sourceCandidateFactory->forMacro($indicatorKey, $priorities, 'macro');

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
