<?php

declare(strict_types=1);

namespace app\dto\report;

/**
 * Complete analysis report for Phase 3 rendering.
 *
 * This is the primary output of Phase 2: Analysis.
 */
final readonly class ReportDTO
{
    public function __construct(
        public ReportMetadata $metadata,
        public FocalAnalysis $focalAnalysis,
        public FinancialsSummary $financials,
        public PeerComparison $peerComparison,
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
            'focal_analysis' => $this->focalAnalysis->toArray(),
            'financials' => $this->financials->toArray(),
            'peer_comparison' => $this->peerComparison->toArray(),
            'macro' => $this->macro->toArray(),
        ];
    }
}
