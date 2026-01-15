<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\industry\PdfEligibility;

/**
 * Query for determining PDF viewing eligibility for an industry.
 */
final class IndustryPdfEligibilityQuery
{
    private const DISABLED_REASON_NO_REPORT = 'No analysis report exists';

    public function __construct(
        private readonly AnalysisReportReader $reportReader,
    ) {
    }

    public function getEligibility(int $industryId): PdfEligibility
    {
        $latestReport = $this->reportReader->getLatestRanking($industryId);

        if ($latestReport === null) {
            return new PdfEligibility(
                hasReport: false,
                disabledReason: self::DISABLED_REASON_NO_REPORT,
            );
        }

        return new PdfEligibility(
            hasReport: true,
            disabledReason: null,
        );
    }
}
