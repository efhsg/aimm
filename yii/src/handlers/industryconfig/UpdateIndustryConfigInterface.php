<?php

declare(strict_types=1);

namespace app\handlers\industryconfig;

use app\dto\industryconfig\SaveIndustryConfigResult;
use app\dto\industryconfig\UpdateIndustryConfigRequest;

/**
 * Updates an existing industry config record.
 */
interface UpdateIndustryConfigInterface
{
    public function update(UpdateIndustryConfigRequest $request): SaveIndustryConfigResult;
}
