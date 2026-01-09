<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\CreateIndustryRequest;
use app\dto\industry\SaveIndustryResult;

/**
 * Creates a new industry.
 */
interface CreateIndustryInterface
{
    public function create(CreateIndustryRequest $request): SaveIndustryResult;
}
