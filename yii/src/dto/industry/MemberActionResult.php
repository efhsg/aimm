<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Result DTO for single-member actions (e.g., remove member).
 */
final readonly class MemberActionResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public array $errors = [],
    ) {
    }

    public static function success(): self
    {
        return new self(success: true);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(success: false, errors: $errors);
    }
}
