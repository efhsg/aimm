<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\analysis\RatingDeterminationResult;
use app\dto\report\FundamentalsBreakdown;
use app\dto\report\RiskBreakdown;
use app\dto\report\ValuationGapSummary;

interface DetermineRatingInterface
{
    public function handle(
        FundamentalsBreakdown $fundamentals,
        RiskBreakdown $risk,
        ValuationGapSummary $valuationGap,
        AnalysisThresholds $thresholds
    ): RatingDeterminationResult;
}
