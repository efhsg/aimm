<?php

declare(strict_types=1);

namespace app\dto;

use app\enums\CollectionStatus;

/**
 * Output DTO for macro indicator collection.
 */
final readonly class CollectMacroResult
{
    /**
     * @param SourceAttempt[] $sourceAttempts All fetch attempts made during collection
     */
    public function __construct(
        public MacroData $data,
        public array $sourceAttempts,
        public CollectionStatus $status,
    ) {
    }
}
