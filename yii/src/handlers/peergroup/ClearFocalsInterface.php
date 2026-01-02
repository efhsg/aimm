<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\ClearFocalsRequest;
use app\dto\peergroup\MemberActionResult;

/**
 * Interface for clearing all focal designations from a peer group.
 */
interface ClearFocalsInterface
{
    /**
     * Clear all focal designations in a peer group.
     */
    public function clearFocals(ClearFocalsRequest $request): MemberActionResult;
}
