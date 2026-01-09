<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Financial metrics for PDF reports.
 */
final readonly class FinancialsDto
{
    /**
     * @param MetricRowDto[] $metrics
     */
    public function __construct(
        public array $metrics,
    ) {
    }
}
