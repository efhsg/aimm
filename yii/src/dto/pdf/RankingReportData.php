<?php

declare(strict_types=1);

namespace app\dto\pdf;

use DateTimeImmutable;

/**
 * Complete ranking report data for PDF generation.
 *
 * This DTO contains all companies ranked, matching the web ranking page.
 */
final readonly class RankingReportData
{
    /**
     * @param CompanyRankingDto[] $companyRankings
     */
    public function __construct(
        public string $reportId,
        public string $traceId,
        public string $industryName,
        public RankingMetadataDto $metadata,
        public GroupAveragesDto $groupAverages,
        public array $companyRankings,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
