<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;

interface DeleteDataSourceInterface
{
    public function delete(DeleteDataSourceRequest $request): SaveDataSourceResult;
}
