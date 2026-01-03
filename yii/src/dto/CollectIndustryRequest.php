<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Input DTO for collecting an entire industry.
 */
final readonly class CollectIndustryRequest
{
    /**
     * @param list<string> $focalTickers Additional focal tickers beyond those in config
     */
    public function __construct(
        public IndustryConfig $config,
        public int $batchSize = 10,
        public bool $enableMemoryManagement = true,
        public array $focalTickers = [],
    ) {
    }
}
