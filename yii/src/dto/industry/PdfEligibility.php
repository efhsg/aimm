<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Result of PDF eligibility check for an industry.
 */
final readonly class PdfEligibility
{
    public function __construct(
        public bool $hasReport,
        public ?string $disabledReason,
    ) {
    }

    public function canView(): bool
    {
        return $this->hasReport;
    }
}
