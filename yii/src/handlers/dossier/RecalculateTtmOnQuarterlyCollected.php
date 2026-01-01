<?php

declare(strict_types=1);

namespace app\handlers\dossier;

use app\events\QuarterlyFinancialsCollectedEvent;

/**
 * Event handler that recalculates TTM when quarters are collected.
 */
final class RecalculateTtmOnQuarterlyCollected
{
    public function __construct(
        private readonly TtmCalculator $calculator,
    ) {
    }

    public function handle(QuarterlyFinancialsCollectedEvent $event): void
    {
        $this->calculator->calculate(
            $event->companyId,
            $event->periodEndDate
        );
    }
}
