<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateResult;
use app\dto\IndustryConfig;
use app\dto\IndustryDataPack;

/**
 * Interface for collection gate validation.
 */
interface CollectionGateValidatorInterface
{
    /**
     * Validate an IndustryDataPack before the analysis phase.
     *
     * @param list<string> $focalTickers Tickers of companies treated as focals (receive stricter validation)
     */
    public function validate(IndustryDataPack $dataPack, IndustryConfig $config, array $focalTickers = []): GateResult;

    /**
     * Create a passing gate result (used when validation is skipped).
     */
    public function createPassingResult(): GateResult;

    /**
     * Validate collection results (dossier-based validation).
     *
     * @param array<string, \app\enums\CollectionStatus> $companyStatuses
     * @param list<string> $focalTickers
     */
    public function validateResults(
        array $companyStatuses,
        \app\enums\CollectionStatus $macroStatus,
        \app\dto\IndustryConfig $config,
        array $focalTickers = []
    ): GateResult;
}
