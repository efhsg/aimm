<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\enums\CollectionStatus;

/**
 * Interface for collection gate validation.
 */
interface CollectionGateValidatorInterface
{
    /**
     * Create a passing gate result (used when validation is skipped).
     */
    public function createPassingResult(): GateResult;

    /**
     * Validate collection results (dossier-based validation).
     *
     * @param array<string, CollectionStatus> $companyStatuses
     */
    public function validateResults(
        array $companyStatuses,
        CollectionStatus $macroStatus,
        IndustryConfig $config
    ): GateResult;
}
