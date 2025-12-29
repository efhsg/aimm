<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;
use app\dto\datapoints\DataPointPercent;
use app\dto\datapoints\DataPointRatio;

/**
 * Output DTO for datapoint collection.
 */
final readonly class CollectDatapointResult
{
    /**
     * @param SourceAttempt[] $sourceAttempts All fetch attempts made
     * @param HistoricalExtraction|null $historicalExtraction For period-based data
     * @param FetchResult|null $fetchResult For historical data processing
     */
    public function __construct(
        public string $datapointKey,
        public DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber $datapoint,
        public array $sourceAttempts,
        public bool $found,
        public ?HistoricalExtraction $historicalExtraction = null,
        public ?FetchResult $fetchResult = null,
    ) {
    }

    public function isHistorical(): bool
    {
        return $this->historicalExtraction !== null;
    }
}
