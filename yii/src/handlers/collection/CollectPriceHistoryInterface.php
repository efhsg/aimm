<?php

declare(strict_types=1);

namespace app\handlers\collection;

use app\dto\CollectPriceHistoryRequest;
use app\dto\CollectPriceHistoryResult;

/**
 * Interface for collecting historical stock prices.
 */
interface CollectPriceHistoryInterface
{
    public function collect(CollectPriceHistoryRequest $request): CollectPriceHistoryResult;
}
