<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Configuration for an industry collection.
 *
 * Note: This is a DTO, distinct from the ActiveRecord model app\models\IndustryConfig.
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
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
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
    }
}
