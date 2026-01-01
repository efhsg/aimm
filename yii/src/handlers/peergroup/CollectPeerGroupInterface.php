<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\CollectPeerGroupRequest;
use app\dto\peergroup\CollectPeerGroupResult;

/**
 * Collects data for a peer group.
 *
 * This interface abstracts the underlying CollectIndustryInterface,
 * preventing UI controllers from depending on collection internals.
 */
interface CollectPeerGroupInterface
{
    public function collect(CollectPeerGroupRequest $request): CollectPeerGroupResult;
}
