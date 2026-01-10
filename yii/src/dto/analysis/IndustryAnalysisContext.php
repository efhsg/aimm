<?php

declare(strict_types=1);

namespace app\dto\analysis;

use app\dto\CompanyData;
use app\dto\MacroData;
use DateTimeImmutable;

/**
 * Context for industry analysis, built directly from dossier data.
 */
final readonly class IndustryAnalysisContext
{
    /**
     * @param array<string, CompanyData> $companies Indexed by ticker
     */
    public function __construct(
        public int $industryId,
        public string $industrySlug,
        public DateTimeImmutable $collectedAt,
        public MacroData $macro,
        public array $companies,
    ) {
    }

    public function getCompany(string $ticker): ?CompanyData
    {
        return $this->companies[$ticker] ?? null;
    }

    public function hasCompany(string $ticker): bool
    {
        return isset($this->companies[$ticker]);
    }

    /**
     * @return list<string>
     */
    public function getTickers(): array
    {
        return array_keys($this->companies);
    }
}
