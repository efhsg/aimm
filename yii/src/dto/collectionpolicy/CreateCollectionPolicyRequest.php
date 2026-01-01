<?php

declare(strict_types=1);

namespace app\dto\collectionpolicy;

/**
 * Request to create a collection policy.
 */
final readonly class CreateCollectionPolicyRequest
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $actorUsername,
        public ?string $description = null,
        public int $historyYears = 5,
        public int $quartersToFetch = 8,
        public ?string $valuationMetricsJson = null,
        public ?string $annualFinancialMetricsJson = null,
        public ?string $quarterlyFinancialMetricsJson = null,
        public ?string $operationalMetricsJson = null,
        public ?string $commodityBenchmark = null,
        public ?string $marginProxy = null,
        public ?string $sectorIndex = null,
        public ?string $requiredIndicatorsJson = null,
        public ?string $optionalIndicatorsJson = null,
    ) {
    }
}
