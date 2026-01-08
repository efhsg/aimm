<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Macro-level context indicators.
 */
final readonly class MacroContext
{
    public function __construct(
        public ?float $commodityBenchmark,
        public ?float $marginProxy,
        public ?float $sectorIndex,
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
        ];
    }
}
