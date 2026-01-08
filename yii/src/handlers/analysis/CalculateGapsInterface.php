<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalysisThresholds;
use app\dto\CompanyData;
use app\dto\report\PeerAverages;
use app\dto\report\ValuationGapSummary;

interface CalculateGapsInterface
{
    public function handle(
        CompanyData $focal,
        PeerAverages $peerAverages,
        AnalysisThresholds $thresholds
    ): ValuationGapSummary;
}
