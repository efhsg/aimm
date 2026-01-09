<?php

declare(strict_types=1);

namespace app\queries;

/**
 * Interface for reading analysis reports.
 *
 * Extracted from AnalysisReportRepository to support testing.
 */
interface AnalysisReportReader
{
    /**
     * Find a report by its report ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByReportId(string $reportId): ?array;

    /**
     * Decode the report JSON from a row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function decodeReport(array $row): array;
}
