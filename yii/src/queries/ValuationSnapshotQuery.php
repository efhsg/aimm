<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for daily valuation snapshots with retention tiers.
 */
class ValuationSnapshotQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByCompanyAndDate(int $companyId, DateTimeImmutable $date): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM valuation_snapshot 
             WHERE company_id = :companyId AND snapshot_date = :date'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':date' => $date->format('Y-m-d')
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findLatestByCompany(int $companyId): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM valuation_snapshot 
             WHERE company_id = :companyId 
             ORDER BY snapshot_date DESC 
             LIMIT 1'
        )
            ->bindValue(':companyId', $companyId)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('valuation_snapshot', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function upsert(array $data): void
    {
        $this->db->createCommand()
            ->upsert('valuation_snapshot', $data, $data) // Update all fields with new values
            ->execute();
    }
}
