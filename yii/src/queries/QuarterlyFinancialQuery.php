<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for quarterly financial records with TTM support.
 */
class QuarterlyFinancialQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findLastFourQuarters(int $companyId, DateTimeImmutable $asOfDate): array
    {
        return $this->db->createCommand(
            'SELECT * FROM quarterly_financial 
             WHERE company_id = :companyId 
               AND period_end_date <= :date 
               AND is_current = TRUE
             ORDER BY period_end_date DESC 
             LIMIT 4'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':date' => $asOfDate->format('Y-m-d')
            ])
            ->queryAll();
    }

    public function findCurrentByCompanyAndQuarter(int $companyId, int $year, int $quarter): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM quarterly_financial 
             WHERE company_id = :companyId 
               AND fiscal_year = :year 
               AND fiscal_quarter = :quarter
               AND is_current = TRUE'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':year' => $year,
                ':quarter' => $quarter
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findAllCurrentByCompany(int $companyId): array
    {
        return $this->db->createCommand(
            'SELECT * FROM quarterly_financial 
             WHERE company_id = :companyId AND is_current = TRUE 
             ORDER BY fiscal_year DESC, fiscal_quarter DESC'
        )
            ->bindValue(':companyId', $companyId)
            ->queryAll();
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('quarterly_financial', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }
}
