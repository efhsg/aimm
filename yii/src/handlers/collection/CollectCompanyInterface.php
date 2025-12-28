<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectCompanyRequest;
use app\dto\CollectCompanyResult;

/**
 * Collects all datapoints for a single company.
 */
interface CollectCompanyInterface
{
    public function collect(CollectCompanyRequest $request): CollectCompanyResult;
}
