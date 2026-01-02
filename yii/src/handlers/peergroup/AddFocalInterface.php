<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\AddFocalRequest;
use app\dto\peergroup\MemberActionResult;

/**
 * Interface for adding a focal designation to a peer group member.
 */
interface AddFocalInterface
{
    /**
     * Add focal designation to a company without clearing existing focals.
     */
    public function addFocal(AddFocalRequest $request): MemberActionResult;
}
