<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\GateResult;
use app\dto\IndustryDataPack;

interface AnalysisGateValidatorInterface
{
    public function validate(IndustryDataPack $dataPack): GateResult;
}
