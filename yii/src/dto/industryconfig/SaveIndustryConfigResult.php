<?php

declare(strict_types=1);

namespace app\dto\industryconfig;

/**
 * Result DTO for create/update/toggle operations.
 */
final readonly class SaveIndustryConfigResult
{
    /**
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public ?IndustryConfigResponse $config = null,
        public array $errors = [],
    ) {
    }

    public static function success(IndustryConfigResponse $config): self
    {
        return new self(success: true, config: $config);
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
            'config' => $this->config?->toArray(),
            'errors' => $this->errors,
        ];
    }
}
