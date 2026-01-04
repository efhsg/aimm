<?php

declare(strict_types=1);

namespace app\dto;

use app\enums\CollectionStatus;

/**
 * Result of collecting historical stock price data.
 */
final readonly class CollectPriceHistoryResult
{
    /**
     * @param string $ticker The stock ticker
     * @param int $recordsCollected Number of price records collected
     * @param int $recordsInserted Number of new records inserted (excludes duplicates)
     * @param SourceAttempt[] $sourceAttempts Collection attempts made
     * @param CollectionStatus $status Overall collection status
     * @param string|null $error Error message if failed
     */
    public function __construct(
        public string $ticker,
        public int $recordsCollected,
        public int $recordsInserted,
        public array $sourceAttempts,
        public CollectionStatus $status,
        public ?string $error = null,
    ) {
    }
}
