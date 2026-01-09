<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\CollectIndustryRequest;
use app\dto\industry\CollectIndustryResult;

/**
 * Collects data for an industry.
 *
 * This interface abstracts the underlying collection internals,
 * preventing UI controllers from depending on collection implementation.
 */
interface CollectIndustryInterface
{
    public function collect(CollectIndustryRequest $request): CollectIndustryResult;
}
