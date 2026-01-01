<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\CreateCollectionPolicyRequest;

/**
 * Creates a new collection policy.
 */
interface CreateCollectionPolicyInterface
{
    public function create(CreateCollectionPolicyRequest $request): CollectionPolicyResult;
}
