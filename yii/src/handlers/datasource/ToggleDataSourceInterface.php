<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\ToggleDataSourceRequest;

interface ToggleDataSourceInterface
{
    public function toggle(ToggleDataSourceRequest $request): SaveDataSourceResult;
}
