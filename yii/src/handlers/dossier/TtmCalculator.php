<?php

declare(strict_types=1);

namespace app\handlers\dossier;

use app\dto\TtmFinancialRecord;
use app\queries\QuarterlyFinancialQuery;
use app\queries\TtmFinancialQuery;
use DateTimeImmutable;

/**
 * Calculates TTM financials from four consecutive quarters.
 */
final class TtmCalculator
{
    public function __construct(
        private readonly QuarterlyFinancialQuery $quarterlyQuery,
        private readonly TtmFinancialQuery $ttmQuery,
    ) {
    }

    public function calculate(int $companyId, DateTimeImmutable $asOfDate): ?TtmFinancialRecord
    {
        $quarters = $this->quarterlyQuery->findLastFourQuarters($companyId, $asOfDate);

        if (count($quarters) < 4) {
            return null;
        }

        if (!$this->areConsecutive($quarters)) {
            return null;
        }

        // Note: Raw query returns strings for decimals/dates. Must cast.
        $ttm = new TtmFinancialRecord(
            companyId: $companyId,
            asOfDate: $asOfDate,
            revenue: $this->sumField($quarters, 'revenue'),
            grossProfit: $this->sumField($quarters, 'gross_profit'),
            operatingIncome: $this->sumField($quarters, 'operating_income'),
            ebitda: $this->sumField($quarters, 'ebitda'),
            netIncome: $this->sumField($quarters, 'net_income'),
            operatingCashFlow: $this->sumField($quarters, 'operating_cash_flow'),
            capex: $this->sumField($quarters, 'capex'),
            freeCashFlow: $this->sumField($quarters, 'free_cash_flow'),
            latestQuarterEnd: new DateTimeImmutable($quarters[0]['period_end_date']),
            previousQuarterEnd: new DateTimeImmutable($quarters[1]['period_end_date']),
            twoQuartersAgoEnd: new DateTimeImmutable($quarters[2]['period_end_date']),
            oldestQuarterEnd: new DateTimeImmutable($quarters[3]['period_end_date']),
            currency: $quarters[0]['currency'],
            calculatedAt: new DateTimeImmutable(),
        );

        $this->ttmQuery->upsert($ttm);

        return $ttm;
    }

    private function areConsecutive(array $quarters): bool
    {
        for ($i = 1; $i < count($quarters); $i++) {
            $prev = new DateTimeImmutable($quarters[$i - 1]['period_end_date']);
            $curr = new DateTimeImmutable($quarters[$i]['period_end_date']);
            // Previous in array is actually LATER in time (DESC sort)
            // quarters[0] is latest (e.g., Dec), quarters[1] is previous (Sep)
            // So diff prev - curr should be ~90 days

            $diffDays = $curr->diff($prev)->days;

            if ($diffDays < 80 || $diffDays > 100) {
                return false;
            }
        }
        return true;
    }

    private function sumField(array $quarters, string $field): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($quarters as $q) {
            if (isset($q[$field]) && $q[$field] !== null) {
                $sum += (float) $q[$field];
                $hasValue = true;
            }
        }

        return $hasValue ? $sum : null;
    }
}
