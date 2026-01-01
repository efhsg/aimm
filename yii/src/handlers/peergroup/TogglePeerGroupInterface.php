<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\SavePeerGroupResult;
use app\dto\peergroup\TogglePeerGroupRequest;

/**
 * Toggles peer group active status.
 */
interface TogglePeerGroupInterface
{
    public function toggle(TogglePeerGroupRequest $request): SavePeerGroupResult;
}
