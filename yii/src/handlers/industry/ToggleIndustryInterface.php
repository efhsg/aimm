<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\SaveIndustryResult;
use app\dto\industry\ToggleIndustryRequest;

/**
 * Toggles an industry's active status.
 */
interface ToggleIndustryInterface
{
    public function toggle(ToggleIndustryRequest $request): SaveIndustryResult;
}
