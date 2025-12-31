<?php

declare(strict_types=1);

namespace app\handlers\industryconfig;

use app\dto\industryconfig\CreateIndustryConfigRequest;
use app\dto\industryconfig\SaveIndustryConfigResult;

/**
 * Creates a new industry config record.
 */
interface CreateIndustryConfigInterface
{
    public function create(CreateIndustryConfigRequest $request): SaveIndustryConfigResult;
}
