<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectDatapointRequest;
use app\dto\CollectDatapointResult;

/**
 * Collects a single datapoint from prioritized sources.
 */
interface CollectDatapointInterface
{
    public function collect(CollectDatapointRequest $request): CollectDatapointResult;
}
