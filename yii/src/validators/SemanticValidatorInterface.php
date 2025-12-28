<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateResult;
use app\dto\IndustryDataPack;

/**
 * Interface for semantic validation of datapack values.
 */
interface SemanticValidatorInterface
{
    /**
     * Validate datapack values against domain-specific rules.
     */
    public function validate(IndustryDataPack $dataPack): GateResult;
}
