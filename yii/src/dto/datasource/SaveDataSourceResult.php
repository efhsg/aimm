<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class SaveDataSourceResult
{
    /**
     * @param array<string, mixed>|null $dataSource
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public ?array $dataSource = null,
        public array $errors = [],
    ) {
    }

    public static function success(array $dataSource): self
    {
        return new self(true, $dataSource);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
