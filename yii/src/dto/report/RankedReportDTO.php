<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Complete ranked analysis report for all companies in an industry.
 *
 * This is the primary output of Phase 2: Analysis.
 * Companies are sorted by rank (rating priority, then fundamentals score).
 */
final readonly class RankedReportDTO
{
    /**
     * @param CompanyAnalysis[] $companyAnalyses Sorted by rank (1 = best)
     */
    public function __construct(
        public RankedReportMetadata $metadata,
        public array $companyAnalyses,
        public PeerAverages $groupAverages,
        public MacroContext $macro,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata->toArray(),
            'company_analyses' => array_map(
                static fn (CompanyAnalysis $analysis): array => $analysis->toArray(),
                $this->companyAnalyses
            ),
            'group_averages' => $this->groupAverages->toArray(),
            'macro' => $this->macro->toArray(),
        ];
    }
}
