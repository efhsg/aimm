<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * Report metadata for ranking PDF.
 */
final readonly class RankingMetadataDto
{
    public function __construct(
        public int $companyCount,
        public string $generatedAt,
        public string $dataAsOf,
        public string $reportId,
    ) {
    }
}
