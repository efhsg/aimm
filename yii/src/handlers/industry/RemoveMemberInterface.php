<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\MemberActionResult;
use app\dto\industry\RemoveMemberRequest;

/**
 * Removes a member company from an industry.
 */
interface RemoveMemberInterface
{
    public function remove(RemoveMemberRequest $request): MemberActionResult;
}
