<?php

declare(strict_types=1);

namespace app\dto\analysis;

use app\enums\Rating;
use app\enums\RatingRulePath;

/**
 * Result of rating determination with audit trail.
 */
final readonly class RatingDeterminationResult
{
    public function __construct(
        public Rating $rating,
        public RatingRulePath $rulePath,
    ) {
    }
}
