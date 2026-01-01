<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for an industry collection.
 *
 * Built from peer group + collection policy for use by collection handlers.
 */
final readonly class IndustryConfig
{
    /**
     * @param CompanyConfig[] $companies
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $sector,
        public array $companies,
        public MacroRequirements $macroRequirements,
        public DataRequirements $dataRequirements,
        public ?string $focalTicker = null,
    ) {
    }

    /**
     * Resolves the focal ticker with consistent priority across all consumers.
     *
     * Priority:
     * 1. Explicit override (CLI --focal parameter)
     * 2. Config focal_ticker
     * 3. First company in the list
     *
     * @param string|null $override Explicit override (e.g., from CLI)
     * @param bool $usedFallback Output parameter: true if first-company fallback was used
     */
    public function resolveFocalTicker(?string $override = null, bool &$usedFallback = false): ?string
    {
        $usedFallback = false;
        $configuredTickers = array_map(static fn ($c): string => $c->ticker, $this->companies);

        // Priority 1: Explicit override
        if (is_string($override) && $override !== '' && in_array($override, $configuredTickers, true)) {
            return $override;
        }

        // Priority 2: Config focal_ticker
        if (is_string($this->focalTicker) && $this->focalTicker !== '' && in_array($this->focalTicker, $configuredTickers, true)) {
            return $this->focalTicker;
        }

        // Priority 3: First company (fallback)
        $firstCompany = $this->companies[0] ?? null;
        if ($firstCompany !== null) {
            $usedFallback = true;
            return $firstCompany->ticker;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'sector' => $this->sector,
            'companies' => array_map(
                fn (CompanyConfig $c) => $c->toArray(),
                $this->companies,
            ),
            'macro_requirements' => $this->macroRequirements->toArray(),
            'data_requirements' => $this->dataRequirements->toArray(),
        ];

        if ($this->focalTicker !== null) {
            $result['focal_ticker'] = $this->focalTicker;
        }

        return $result;
    }
}
