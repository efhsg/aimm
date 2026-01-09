<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\report\CompanyAnalysis;
use app\enums\Rating;

/**
 * Ranks companies by rating priority, then by fundamentals score.
 */
final class RankCompaniesHandler implements RankCompaniesInterface
{
    private const RATING_PRIORITY = [
        'buy' => 1,
        'hold' => 2,
        'sell' => 3,
    ];

    /**
     * @param CompanyAnalysis[] $analyses
     * @return CompanyAnalysis[]
     */
    public function handle(array $analyses): array
    {
        if ($analyses === []) {
            return [];
        }

        // Sort by rating priority, then by fundamentals score (descending)
        usort($analyses, function (CompanyAnalysis $a, CompanyAnalysis $b): int {
            $ratingCompare = $this->getRatingPriority($a->rating) <=> $this->getRatingPriority($b->rating);

            if ($ratingCompare !== 0) {
                return $ratingCompare;
            }

            // Higher fundamentals score is better (sort descending)
            return $b->fundamentals->compositeScore <=> $a->fundamentals->compositeScore;
        });

        // Create new CompanyAnalysis objects with proper ranks
        $ranked = [];
        foreach ($analyses as $index => $analysis) {
            $ranked[] = new CompanyAnalysis(
                ticker: $analysis->ticker,
                name: $analysis->name,
                rating: $analysis->rating,
                rulePath: $analysis->rulePath,
                valuation: $analysis->valuation,
                valuationGap: $analysis->valuationGap,
                fundamentals: $analysis->fundamentals,
                risk: $analysis->risk,
                rank: $index + 1,
            );
        }

        return $ranked;
    }

    private function getRatingPriority(Rating $rating): int
    {
        return self::RATING_PRIORITY[$rating->value] ?? 99;
    }
}
