<?php

declare(strict_types=1);

namespace app\validators;

use app\dto\analysis\IndustryAnalysisContext;
use app\dto\GateResult;

interface AnalysisGateValidatorInterface
{
    public function validate(IndustryAnalysisContext $context): GateResult;
}
