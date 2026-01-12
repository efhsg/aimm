<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\industry\AnalysisEligibility;

/**
 * Query for determining if an industry is eligible for analysis.
 */
final class IndustryAnalysisEligibilityQuery
{
    private const DISABLED_REASON_NO_DATA = 'No data collected';
    private const DISABLED_REASON_INCOMPLETE = 'Data not complete';

    public function __construct(
        private readonly CollectionRunRepository $collectionRunRepository,
    ) {
    }

    public function getEligibility(int $industryId): AnalysisEligibility
    {
        $latestCompletedRun = $this->collectionRunRepository->getLatestCompleted($industryId);

        $hasCollectedData = $latestCompletedRun !== null;
        $allDossiersGatePassed = $hasCollectedData && (bool) $latestCompletedRun['gate_passed'];

        $disabledReason = $this->determineDisabledReason($hasCollectedData, $allDossiersGatePassed);

        return new AnalysisEligibility(
            hasCollectedData: $hasCollectedData,
            allDossiersGatePassed: $allDossiersGatePassed,
            disabledReason: $disabledReason,
        );
    }

    private function determineDisabledReason(bool $hasCollectedData, bool $allDossiersGatePassed): ?string
    {
        if (!$hasCollectedData) {
            return self::DISABLED_REASON_NO_DATA;
        }

        if (!$allDossiersGatePassed) {
            return self::DISABLED_REASON_INCOMPLETE;
        }

        return null;
    }
}
