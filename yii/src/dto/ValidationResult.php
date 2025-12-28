<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Result of JSON schema validation.
 */
final readonly class ValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors,
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
