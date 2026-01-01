<?php

declare(strict_types=1);

namespace app\dto\collectionpolicy;

/**
 * Result of a collection policy operation.
 */
final readonly class CollectionPolicyResult
{
    /**
     * @param array<string, mixed>|null $policy
     * @param string[] $errors
     */
    private function __construct(
        public bool $success,
        public ?array $policy,
        public array $errors,
    ) {
    }

    /**
     * @param array<string, mixed> $policy
     */
    public static function success(array $policy): self
    {
        return new self(
            success: true,
            policy: $policy,
            errors: [],
        );
    }

    public static function deleted(): self
    {
        return new self(
            success: true,
            policy: null,
            errors: [],
        );
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(
            success: false,
            policy: null,
            errors: $errors,
        );
    }
}
