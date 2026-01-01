<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\MemberActionResult;
use app\dto\peergroup\RemoveMemberRequest;

/**
 * Removes a member from a peer group.
 */
interface RemoveMemberInterface
{
    public function remove(RemoveMemberRequest $request): MemberActionResult;
}
