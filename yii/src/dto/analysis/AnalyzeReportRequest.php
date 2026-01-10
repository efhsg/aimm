<?php

declare(strict_types=1);

namespace app\dto\analysis;

/**
 * Request to analyze an industry and rank all companies.
 */
final readonly class AnalyzeReportRequest
{
    public function __construct(
        public IndustryAnalysisContext $context,
        public string $industrySlug,
        public string $industryName,
        public AnalysisThresholds $thresholds = new AnalysisThresholds(),
    ) {
    }
}
