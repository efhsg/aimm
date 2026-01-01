<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

final class DataGapQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByCompanyAndType(int $companyId, string $dataType): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM data_gap 
             WHERE company_id = :companyId AND data_type = :type'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':type' => $dataType
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function upsert(array $data): void
    {
        // Upsert to increment check_count and update last_checked if exists
        $sql = <<<SQL
            INSERT INTO data_gap (
                company_id, data_type, gap_reason, first_detected, last_checked, check_count, notes
            ) VALUES (
                :companyId, :dataType, :gapReason, :firstDetected, :lastChecked, :checkCount, :notes
            ) ON DUPLICATE KEY UPDATE
                gap_reason = VALUES(gap_reason),
                last_checked = VALUES(last_checked),
                check_count = check_count + 1,
                notes = VALUES(notes)
        SQL;

        $this->db->createCommand($sql)
            ->bindValues([
                ':companyId' => $data['company_id'],
                ':dataType' => $data['data_type'],
                ':gapReason' => $data['gap_reason'],
                ':firstDetected' => $data['first_detected'],
                ':lastChecked' => $data['last_checked'],
                ':checkCount' => $data['check_count'] ?? 1,
                ':notes' => $data['notes'] ?? null,
            ])
            ->execute();
    }

    public function delete(int $companyId, string $dataType): void
    {
        $this->db->createCommand(
            'DELETE FROM data_gap WHERE company_id = :companyId AND data_type = :type'
        )
            ->bindValues([
                ':companyId' => $companyId,
                ':type' => $dataType
            ])
            ->execute();
    }
}
