<?php

declare(strict_types=1);

namespace app\adapters;

use app\dto\AdaptRequest;
use app\dto\AdaptResult;
use app\exceptions\BlockedException;
use Throwable;
use yii\log\Logger;

/**
 * Priority-ordered adapter fallback chain.
 *
 * Tries adapters in order, skips blocked ones, and merges partial extraction results.
 */
final class AdapterChain implements SourceAdapterInterface
{
    private const ADAPTER_ID = 'chain';

    /**
     * @param SourceAdapterInterface[] $adapters Priority-ordered list
     */
    public function __construct(
        private readonly array $adapters,
        private readonly BlockedSourceRegistry $blockedRegistry,
        private readonly Logger $logger,
    ) {
    }

    public function getAdapterId(): string
    {
        return self::ADAPTER_ID;
    }

    public function getSupportedKeys(): array
    {
        $keys = [];
        foreach ($this->adapters as $adapter) {
            $keys = array_merge($keys, $adapter->getSupportedKeys());
        }
        return array_unique($keys);
    }

    public function adapt(AdaptRequest $request): AdaptResult
    {
        $allExtractions = [];
        $allHistoricalExtractions = [];
        $allNotFound = $request->datapointKeys;
        $errors = [];

        foreach ($this->adapters as $adapter) {
            $adapterId = $adapter->getAdapterId();

            if ($this->blockedRegistry->isBlocked($adapterId)) {
                $this->logger->log(
                    ['message' => 'Skipping blocked adapter', 'adapter' => $adapterId],
                    Logger::LEVEL_INFO,
                    'collection'
                );
                continue;
            }

            $remainingKeys = array_intersect($allNotFound, $adapter->getSupportedKeys());
            if (empty($remainingKeys)) {
                continue;
            }

            try {
                $result = $adapter->adapt(new AdaptRequest(
                    fetchResult: $request->fetchResult,
                    datapointKeys: array_values($remainingKeys),
                    ticker: $request->ticker,
                ));

                foreach ($result->extractions as $key => $extraction) {
                    $allExtractions[$key] = $extraction;
                    $allNotFound = array_diff($allNotFound, [$key]);
                }

                foreach ($result->historicalExtractions as $key => $historicalExtraction) {
                    $allHistoricalExtractions[$key] = $historicalExtraction;
                    $allNotFound = array_diff($allNotFound, [$key]);
                }

                if ($result->parseError !== null) {
                    $errors[] = "[{$adapterId}] {$result->parseError}";
                }
            } catch (BlockedException $e) {
                $this->blockedRegistry->block($adapterId, $e->retryAfter);
                $errors[] = "[{$adapterId}] Blocked: {$e->getMessage()}";
                continue;
            } catch (Throwable $e) {
                $errors[] = "[{$adapterId}] Error: {$e->getMessage()}";
                continue;
            }

            if (empty($allNotFound)) {
                break;
            }
        }

        return new AdaptResult(
            adapterId: self::ADAPTER_ID,
            extractions: $allExtractions,
            notFound: array_values($allNotFound),
            parseError: empty($errors) ? null : implode('; ', $errors),
            historicalExtractions: $allHistoricalExtractions,
        );
    }
}
