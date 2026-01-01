<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\TtmFinancialRecord;
use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for trailing twelve month (TTM) financial records.
 */
class TtmFinancialQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByCompanyAndDate(int $companyId, DateTimeImmutable $date): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM ttm_financial 
             WHERE company_id = :companyId AND as_of_date = :date'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':date' => $date->format('Y-m-d')
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function upsert(TtmFinancialRecord $record): void
    {
        $sql = <<<SQL
            INSERT INTO ttm_financial (
                company_id, as_of_date, revenue, gross_profit, operating_income, ebitda, net_income,
                operating_cash_flow, capex, free_cash_flow,
                q1_period_end, q2_period_end, q3_period_end, q4_period_end,
                currency, calculated_at
            ) VALUES (
                :companyId, :asOfDate, :revenue, :grossProfit, :operatingIncome, :ebitda, :netIncome,
                :operatingCashFlow, :capex, :freeCashFlow,
                :q1, :q2, :q3, :q4,
                :currency, :calculatedAt
            ) ON DUPLICATE KEY UPDATE
                revenue = VALUES(revenue),
                gross_profit = VALUES(gross_profit),
                operating_income = VALUES(operating_income),
                ebitda = VALUES(ebitda),
                net_income = VALUES(net_income),
                operating_cash_flow = VALUES(operating_cash_flow),
                capex = VALUES(capex),
                free_cash_flow = VALUES(free_cash_flow),
                q1_period_end = VALUES(q1_period_end),
                q2_period_end = VALUES(q2_period_end),
                q3_period_end = VALUES(q3_period_end),
                q4_period_end = VALUES(q4_period_end),
                currency = VALUES(currency),
                calculated_at = VALUES(calculated_at)
        SQL;

        $this->db->createCommand($sql)
            ->bindValues([
                ':companyId' => $record->companyId,
                ':asOfDate' => $record->asOfDate->format('Y-m-d'),
                ':revenue' => $record->revenue,
                ':grossProfit' => $record->grossProfit,
                ':operatingIncome' => $record->operatingIncome,
                ':ebitda' => $record->ebitda,
                ':netIncome' => $record->netIncome,
                ':operatingCashFlow' => $record->operatingCashFlow,
                ':capex' => $record->capex,
                ':freeCashFlow' => $record->freeCashFlow,
                ':q1' => $record->latestQuarterEnd?->format('Y-m-d'),
                ':q2' => $record->previousQuarterEnd?->format('Y-m-d'),
                ':q3' => $record->twoQuartersAgoEnd?->format('Y-m-d'),
                ':q4' => $record->oldestQuarterEnd?->format('Y-m-d'),
                ':currency' => $record->currency,
                ':calculatedAt' => $record->calculatedAt->format('Y-m-d H:i:s'),
            ])
            ->execute();
    }
}
