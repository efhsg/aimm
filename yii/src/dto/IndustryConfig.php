<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for an industry collection.
 *
 * Built from industry + collection policy for use by collection handlers.
 */
final readonly class IndustryConfig
{
    /**
     * @param CompanyConfig[] $companies
     */
    public function __construct(
        public int $industryId,
        public string $id,
        public string $name,
        public string $sector,
        public array $companies,
        public MacroRequirements $macroRequirements,
        public DataRequirements $dataRequirements,
        public ?SourcePriorities $sourcePriorities = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'industry_id' => $this->industryId,
            'id' => $this->id,
            'name' => $this->name,
            'sector' => $this->sector,
            'companies' => array_map(
                fn (CompanyConfig $c) => $c->toArray(),
                $this->companies,
            ),
            'macro_requirements' => $this->macroRequirements->toArray(),
            'data_requirements' => $this->dataRequirements->toArray(),
            'source_priorities' => $this->sourcePriorities?->toArray(),
        ];
    }
}
