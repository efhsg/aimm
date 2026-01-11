<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\UpdateDataSourceRequest;

interface UpdateDataSourceInterface
{
    public function update(UpdateDataSourceRequest $request): SaveDataSourceResult;
}
