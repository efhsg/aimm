<?php

declare(strict_types=1);

namespace app\dto\pdf;

use DateTimeImmutable;

/**
 * Complete report data for PDF generation.
 *
 * This is the main DTO passed to the view renderer.
 */
final readonly class ReportData
{
    /**
     * @param ChartDto[] $charts
     */
    public function __construct(
        public string $reportId,
        public string $traceId,
        public CompanyDto $company,
        public FinancialsDto $financials,
        public PeerGroupDto $peerGroup,
        public array $charts,
        public DateTimeImmutable $generatedAt,
    ) {
    }
}
