<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for macro indicator collection requirements.
 */
final readonly class MacroRequirements
{
    /**
     * @param list<string> $requiredIndicators
     * @param list<string> $optionalIndicators
     */
    public function __construct(
        public ?string $commodityBenchmark = null,
        public ?string $marginProxy = null,
        public ?string $sectorIndex = null,
        public array $requiredIndicators = [],
        public array $optionalIndicators = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'commodity_benchmark' => $this->commodityBenchmark,
            'margin_proxy' => $this->marginProxy,
            'sector_index' => $this->sectorIndex,
            'required_indicators' => $this->requiredIndicators,
            'optional_indicators' => $this->optionalIndicators,
        ];
    }
}
