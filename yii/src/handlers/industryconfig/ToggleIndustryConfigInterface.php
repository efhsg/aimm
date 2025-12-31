<?php

declare(strict_types=1);

namespace app\handlers\industryconfig;

use app\dto\industryconfig\SaveIndustryConfigResult;
use app\dto\industryconfig\ToggleIndustryConfigRequest;

/**
 * Toggles the is_active status of an industry config.
 */
interface ToggleIndustryConfigInterface
{
    public function toggle(ToggleIndustryConfigRequest $request): SaveIndustryConfigResult;
}
