<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\RiskThresholds;
use app\dto\CompanyData;
use app\dto\report\RiskBreakdown;

interface AssessRiskInterface
{
    public function handle(
        CompanyData $focal,
        RiskThresholds $thresholds
    ): RiskBreakdown;
}
