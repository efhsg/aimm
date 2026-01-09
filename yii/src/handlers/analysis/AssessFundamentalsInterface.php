<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\FundamentalsWeights;
use app\dto\CompanyData;
use app\dto\report\FundamentalsBreakdown;

interface AssessFundamentalsInterface
{
    public function handle(
        CompanyData $company,
        FundamentalsWeights $weights
    ): FundamentalsBreakdown;
}
