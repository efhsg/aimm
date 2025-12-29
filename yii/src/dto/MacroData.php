<?php

declare(strict_types=1);

namespace app\dto;

use app\dto\datapoints\DataPointMoney;
use app\dto\datapoints\DataPointNumber;

/**
 * Macro-level indicators for an industry.
 */
final readonly class MacroData
{
    /**
     * @param array<string, DataPointMoney|DataPointNumber> $additionalIndicators
     */
    public function __construct(
        public ?DataPointMoney $commodityBenchmark = null,
        public ?DataPointMoney $marginProxy = null,
        public ?DataPointNumber $sectorIndex = null,
        public array $additionalIndicators = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $additionalIndicators = [];
        foreach ($this->additionalIndicators as $name => $datapoint) {
            $additionalIndicators[$name] = $datapoint->toArray();
        }

        if ($additionalIndicators === []) {
            $additionalIndicators = new \stdClass();
        }

        return [
            'commodity_benchmark' => $this->commodityBenchmark?->toArray(),
            'margin_proxy' => $this->marginProxy?->toArray(),
            'sector_index' => $this->sectorIndex?->toArray(),
            'additional_indicators' => $additionalIndicators,
        ];
    }
}
