<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\UpdateCollectionPolicyRequest;

/**
 * Updates an existing collection policy.
 */
interface UpdateCollectionPolicyInterface
{
    public function update(UpdateCollectionPolicyRequest $request): CollectionPolicyResult;
}
