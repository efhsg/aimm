<?php

declare(strict_types=1);

namespace app\transformers;

use app\dto\CollectionLog;
use app\dto\MacroData;
use DateTimeImmutable;

interface DataPackAssemblerInterface
{
    public function assemble(
        string $industryId,
        string $datapackId,
        MacroData $macro,
        CollectionLog $collectionLog,
        DateTimeImmutable $collectedAt,
    ): string;
}
