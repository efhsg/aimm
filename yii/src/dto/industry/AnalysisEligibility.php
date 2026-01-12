<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Result of analysis eligibility check for an industry.
 */
final readonly class AnalysisEligibility
{
    public function __construct(
        public bool $hasCollectedData,
        public bool $allDossiersGatePassed,
        public ?string $disabledReason,
    ) {
    }

    public function isEligible(): bool
    {
        return $this->hasCollectedData && $this->allDossiersGatePassed;
    }
}
