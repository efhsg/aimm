<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\AddMembersRequest;
use app\dto\industry\AddMembersResult;

/**
 * Adds member companies to an industry.
 */
interface AddMembersInterface
{
    public function add(AddMembersRequest $request): AddMembersResult;
}
