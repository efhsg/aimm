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
     */
    public function validate(IndustryDataPack $dataPack, IndustryConfig $config, ?string $focalTicker = null): GateResult;
}
