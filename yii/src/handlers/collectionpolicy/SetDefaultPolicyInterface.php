<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\SetDefaultPolicyRequest;

/**
 * Sets or clears a policy as the sector default.
 */
interface SetDefaultPolicyInterface
{
    public function setDefault(SetDefaultPolicyRequest $request): CollectionPolicyResult;
}
