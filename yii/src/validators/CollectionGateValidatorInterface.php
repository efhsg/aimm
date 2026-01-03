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
}
