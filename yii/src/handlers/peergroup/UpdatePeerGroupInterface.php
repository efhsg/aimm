<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\SavePeerGroupResult;
use app\dto\peergroup\UpdatePeerGroupRequest;

/**
 * Updates an existing peer group.
 */
interface UpdatePeerGroupInterface
{
    public function update(UpdatePeerGroupRequest $request): SavePeerGroupResult;
}
