<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\SaveIndustryResult;
use app\dto\industry\UpdateIndustryRequest;

/**
 * Updates an existing industry.
 */
interface UpdateIndustryInterface
{
    public function update(UpdateIndustryRequest $request): SaveIndustryResult;
}
