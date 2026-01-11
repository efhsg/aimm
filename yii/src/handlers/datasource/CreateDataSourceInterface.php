<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;

interface CreateDataSourceInterface
{
    public function create(CreateDataSourceRequest $request): SaveDataSourceResult;
}
