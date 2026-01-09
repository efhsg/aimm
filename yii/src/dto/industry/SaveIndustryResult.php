<?php

declare(strict_types=1);

namespace app\dto\industry;

/**
 * Result DTO for create/update/toggle industry operations.
 */
final readonly class SaveIndustryResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public ?IndustryResponse $industry = null,
        public array $errors = [],
    ) {
    }

    public static function success(IndustryResponse $industry): self
    {
        return new self(success: true, industry: $industry);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(success: false, errors: $errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'industry' => $this->industry?->toArray(),
            'errors' => $this->errors,
        ];
    }
}
