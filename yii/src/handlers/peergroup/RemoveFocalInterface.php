<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\MemberActionResult;
use app\dto\peergroup\RemoveFocalRequest;

/**
 * Interface for removing a focal designation from a peer group member.
 */
interface RemoveFocalInterface
{
    /**
     * Remove focal designation from a company.
     */
    public function removeFocal(RemoveFocalRequest $request): MemberActionResult;
}
