<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Request to collect data for an industry.
 */
final readonly class CollectIndustryRequest
{
    public function __construct(
        public int $industryId,
        public string $actorUsername,
        public int $batchSize = 10,
        public bool $enableMemoryManagement = true,
        public ?int $runId = null,
    ) {
    }
}
