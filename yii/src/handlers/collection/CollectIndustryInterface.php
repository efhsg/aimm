<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectIndustryRequest;
use app\dto\CollectIndustryResult;

interface CollectIndustryInterface
{
    public function collect(CollectIndustryRequest $request): CollectIndustryResult;
}
