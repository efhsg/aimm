<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectMacroRequest;
use app\dto\CollectMacroResult;

/**
 * Collects macro-level indicators for an industry.
 */
interface CollectMacroInterface
{
    public function collect(CollectMacroRequest $request): CollectMacroResult;
}
