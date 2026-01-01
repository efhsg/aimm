<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\CreatePeerGroupRequest;
use app\dto\peergroup\SavePeerGroupResult;

/**
 * Creates a new peer group.
 */
interface CreatePeerGroupInterface
{
    public function create(CreatePeerGroupRequest $request): SavePeerGroupResult;
}
