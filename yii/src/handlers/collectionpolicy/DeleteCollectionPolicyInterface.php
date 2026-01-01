<?php

declare(strict_types=1);

namespace app\handlers\collectionpolicy;

use app\dto\collectionpolicy\CollectionPolicyResult;
use app\dto\collectionpolicy\DeleteCollectionPolicyRequest;

/**
 * Deletes a collection policy.
 */
interface DeleteCollectionPolicyInterface
{
    public function delete(DeleteCollectionPolicyRequest $request): CollectionPolicyResult;
}
