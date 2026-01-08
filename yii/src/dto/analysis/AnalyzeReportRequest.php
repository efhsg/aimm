<?php

declare(strict_types=1);

namespace app\dto\analysis;

use app\dto\IndustryDataPack;

/**
 * Request to analyze an industry datapack.
 */
final readonly class AnalyzeReportRequest
{
    public function __construct(
        public IndustryDataPack $dataPack,
        public string $focalTicker,
        public AnalysisThresholds $thresholds = new AnalysisThresholds(),
    ) {
    }
}
