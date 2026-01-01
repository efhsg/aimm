<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for macro indicators (rig counts, inventories, etc).
 */
class MacroIndicatorQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findByKeyAndDate(string $key, DateTimeImmutable $date): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM macro_indicator 
             WHERE indicator_key = :key AND indicator_date = :date'
        )
            ->bindValues([
                ':key' => $key,
                ':date' => $date->format('Y-m-d')
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findLatestByKey(string $key): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM macro_indicator 
             WHERE indicator_key = :key 
             ORDER BY indicator_date DESC 
             LIMIT 1'
        )
            ->bindValue(':key', $key)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('macro_indicator', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }
}
