<?php

declare(strict_types=1);

namespace app\factories;

use app\dto\CompanyData;

/**
 * Interface for building CompanyData DTOs from dossier database records.
 */
interface CompanyDataDossierFactoryInterface
{
    /**
     * Build a CompanyData DTO from a company row.
     *
     * @param array<string, mixed> $companyRow From CompanyQuery::findByIndustry()
     */
    public function createFromDossier(array $companyRow): ?CompanyData;
}
