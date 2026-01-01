<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\AddMembersRequest;
use app\dto\peergroup\AddMembersResult;

/**
 * Adds members to a peer group.
 */
interface AddMembersInterface
{
    public function add(AddMembersRequest $request): AddMembersResult;
}
