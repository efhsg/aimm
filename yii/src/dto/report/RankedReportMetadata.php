<?php

declare(strict_types=1);

namespace app\dto\report;

use DateTimeImmutable;

/**
 * Metadata about the ranked analysis report.
 */
final readonly class RankedReportMetadata
{
    public function __construct(
        public string $reportId,
        public string $industryId,
        public string $industrySlug,
        public string $industryName,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $dataAsOf,
        public int $companyCount,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'industry_id' => $this->industryId,
            'industry_slug' => $this->industrySlug,
            'industry_name' => $this->industryName,
            'generated_at' => $this->generatedAt->format(DateTimeImmutable::ATOM),
            'data_as_of' => $this->dataAsOf->format(DateTimeImmutable::ATOM),
            'company_count' => $this->companyCount,
        ];
    }
}
