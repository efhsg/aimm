<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\report\CompanyAnalysis;

interface RankCompaniesInterface
{
    /**
     * Rank companies by rating priority, then by fundamentals score.
     *
     * Ranking rules:
     * 1. Group by rating: BUY (best) > HOLD > SELL (worst)
     * 2. Within each rating group, sort by fundamentals composite score DESC
     * 3. Assign sequential ranks starting from 1
     *
     * @param CompanyAnalysis[] $analyses Analysis objects with rank=0 (placeholder)
     * @return CompanyAnalysis[] New analysis objects with proper ranks assigned
     */
    public function handle(array $analyses): array;
}
