<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class UpdateDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sourceType,
        public string $actorUsername,
        public ?string $baseUrl = null,
        public ?string $notes = null,
    ) {
    }
}
