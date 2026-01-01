<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

final class CollectionAttemptQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('collection_attempt', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function findRecentByEntity(string $entityType, int $entityId, int $limit = 10): array
    {
        return $this->db->createCommand(
            'SELECT * FROM collection_attempt 
             WHERE entity_type = :type AND entity_id = :id 
             ORDER BY attempted_at DESC 
             LIMIT :limit'
        )
            ->bindValues([
                ':type' => $entityType,
                ':id' => $entityId,
                ':limit' => $limit
            ])
            ->queryAll();
    }
}
