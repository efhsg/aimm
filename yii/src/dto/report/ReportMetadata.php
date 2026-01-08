<?php

declare(strict_types=1);

namespace app\dto\report;

use DateTimeImmutable;

/**
 * Metadata about the analysis report.
 */
final readonly class ReportMetadata
{
    public function __construct(
        public string $reportId,
        public string $industryId,
        public string $focalTicker,
        public string $focalName,
        public DateTimeImmutable $generatedAt,
        public DateTimeImmutable $dataAsOf,
        public int $peerCount,
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
            'focal_ticker' => $this->focalTicker,
            'focal_name' => $this->focalName,
            'generated_at' => $this->generatedAt->format(DateTimeImmutable::ATOM),
            'data_as_of' => $this->dataAsOf->format(DateTimeImmutable::ATOM),
            'peer_count' => $this->peerCount,
        ];
    }
}
