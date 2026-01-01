<?php

declare(strict_types=1);

namespace app\dto\collectionpolicy;

/**
 * Request to set or clear a sector default policy.
 */
final readonly class SetDefaultPolicyRequest
{
    public function __construct(
        public int $id,
        public string $sector,
        public string $actorUsername,
        public bool $clear = false,
    ) {
    }
}
