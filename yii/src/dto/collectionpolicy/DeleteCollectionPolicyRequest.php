<?php

declare(strict_types=1);

namespace app\dto\collectionpolicy;

/**
 * Request to delete a collection policy.
 */
final readonly class DeleteCollectionPolicyRequest
{
    public function __construct(
        public int $id,
        public string $actorUsername,
    ) {
    }
}
