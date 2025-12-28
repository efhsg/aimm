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
     */
    public function __construct(
        public string $datapointKey,
        public DataPointMoney|DataPointRatio|DataPointPercent|DataPointNumber $datapoint,
        public array $sourceAttempts,
        public bool $found,
    ) {
    }
}
