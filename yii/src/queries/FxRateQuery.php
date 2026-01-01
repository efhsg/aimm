<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for ECB FX rates with batch loading support.
 */
class FxRateQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findClosestRate(string $quoteCurrency, DateTimeImmutable $asOfDate): ?float
    {
        $row = $this->db->createCommand(
            'SELECT rate FROM fx_rate
             WHERE quote_currency = :currency AND rate_date <= :date
             ORDER BY rate_date DESC LIMIT 1'
        )
            ->bindValues([':currency' => $quoteCurrency, ':date' => $asOfDate->format('Y-m-d')])
            ->queryOne();

        return $row !== false ? (float) $row['rate'] : null;
    }

    public function findRatesInRange(
        array $currencies,
        DateTimeImmutable $minDate,
        DateTimeImmutable $maxDate
    ): array {
        if (empty($currencies)) {
            return [];
        }

        // Note: Yii's bindParam handles arrays for IN clause if using Query builder,
        // but for createCommand with raw SQL, we must handle placeholders or use Query Builder properly.
        // However, raw SQL with placeholders for array is tricky in Yii without Query Builder.
        // The safest way with raw SQL and array binding in Yii is usually not direct.
        // BUT, Yii's `createCommand` DOES NOT automatically expand array params in raw SQL unless using Query Builder syntax.

        // Let's use Query Builder for this one method to be safe and clean, or construct placeholders.
        // The prompt asked for "raw queries", but building safe IN clauses manually is error-prone.
        // I will use `yii\db\Query` just for building the SQL or manually implode placeholders.

        // Manual placeholders approach:
        $placeholders = [];
        $params = [
            ':minDate' => $minDate->format('Y-m-d'),
            ':maxDate' => $maxDate->format('Y-m-d'),
        ];
        foreach ($currencies as $i => $currency) {
            $ph = ":c$i";
            $placeholders[] = $ph;
            $params[$ph] = $currency;
        }
        $inClause = implode(',', $placeholders);

        return $this->db->createCommand(
            "SELECT quote_currency, rate_date, rate FROM fx_rate
             WHERE quote_currency IN ($inClause)
               AND rate_date BETWEEN :minDate AND :maxDate
             ORDER BY quote_currency, rate_date"
        )
            ->bindValues($params)
            ->queryAll();
    }
}
