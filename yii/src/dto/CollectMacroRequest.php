<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Input DTO for collecting macro-level indicators.
 */
final readonly class CollectMacroRequest
{
    public function __construct(
        public MacroRequirements $requirements,
    ) {
    }
}
