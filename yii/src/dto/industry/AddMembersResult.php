<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Result DTO for adding members to an industry.
 */
final readonly class AddMembersResult
{
    /**
     * @param string[] $added
     * @param string[] $skipped
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public array $added = [],
        public array $skipped = [],
        public array $errors = [],
    ) {
    }

    /**
     * @param string[] $added
     * @param string[] $skipped
     */
    public static function success(array $added, array $skipped = []): self
    {
        return new self(success: true, added: $added, skipped: $skipped);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(success: false, errors: $errors);
    }
}
