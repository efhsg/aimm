<?php

declare(strict_types=1);

namespace app\dto\collectionpolicy;

/**
 * Request to update a collection policy.
 */
final readonly class UpdateCollectionPolicyRequest
{
    public function __construct(
        public int $id,
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
        public ?string $sourcePrioritiesJson = null,
    ) {
    }
}
