<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Company information for PDF reports.
 */
final readonly class CompanyDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $ticker,
        public string $industry,
    ) {
    }
}
