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
     * @param list<string> $focalTickers Tickers of companies that should receive full requirements during collection
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $sector,
        public array $companies,
        public MacroRequirements $macroRequirements,
        public DataRequirements $dataRequirements,
        public array $focalTickers = [],
    ) {
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

        if (!empty($this->focalTickers)) {
            $result['focal_tickers'] = $this->focalTickers;
        }

        return $result;
    }
}
