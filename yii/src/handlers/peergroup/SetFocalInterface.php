<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\MemberActionResult;
use app\dto\peergroup\SetFocalRequest;

/**
 * Sets the focal company in a peer group.
 */
interface SetFocalInterface
{
    public function setFocal(SetFocalRequest $request): MemberActionResult;
}
