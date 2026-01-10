<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectBatchRequest;
use app\dto\CollectBatchResult;

/**
 * Interface for batch datapoint collection.
 *
 * Collects multiple datapoints in a single operation with required/optional
 * distinction for early-exit optimization.
 */
interface CollectBatchInterface
{
    public function collect(CollectBatchRequest $request): CollectBatchResult;
}
