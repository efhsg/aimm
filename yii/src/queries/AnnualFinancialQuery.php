<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for annual financial records with versioning support.
 */
class AnnualFinancialQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findCurrentByCompanyAndYear(int $companyId, int $year): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM annual_financial 
             WHERE company_id = :companyId AND fiscal_year = :year AND is_current = TRUE'
        )
            ->bindValues([':companyId' => $companyId, ':year' => $year])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findAllCurrentByCompany(int $companyId): array
    {
        return $this->db->createCommand(
            'SELECT * FROM annual_financial 
             WHERE company_id = :companyId AND is_current = TRUE 
             ORDER BY fiscal_year DESC'
        )
            ->bindValue(':companyId', $companyId)
            ->queryAll();
    }

    public function findLatestYear(int $companyId): ?int
    {
        $year = $this->db->createCommand(
            'SELECT MAX(fiscal_year) FROM annual_financial 
             WHERE company_id = :companyId AND is_current = TRUE'
        )
            ->bindValue(':companyId', $companyId)
            ->queryScalar();

        return $year !== false && $year !== null ? (int) $year : null;
    }

    public function exists(int $companyId, int $year): bool
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM annual_financial 
             WHERE company_id = :companyId AND fiscal_year = :year AND is_current = TRUE'
        )
            ->bindValues([':companyId' => $companyId, ':year' => $year])
            ->queryScalar();

        return (int) $count > 0;
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('annual_financial', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function markNotCurrent(int $companyId, int $year): void
    {
        $this->db->createCommand()
            ->update(
                'annual_financial',
                ['is_current' => 0],
                ['company_id' => $companyId, 'fiscal_year' => $year, 'is_current' => 1]
            )
            ->execute();
    }
}
