<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class DeleteDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $actorUsername,
    ) {
    }
}
