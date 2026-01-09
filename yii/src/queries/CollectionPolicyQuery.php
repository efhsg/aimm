<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for collection policies (reusable data collection rules).
 */
class CollectionPolicyQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM collection_policy WHERE id = :id'
        )
            ->bindValue(':id', $id)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM collection_policy WHERE slug = :slug'
        )
            ->bindValue(':slug', $slug)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findAll(): array
    {
        return $this->db->createCommand(
            'SELECT * FROM collection_policy ORDER BY name'
        )
            ->queryAll();
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('collection_policy', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function update(int $id, array $data): void
    {
        $this->db->createCommand()
            ->update('collection_policy', $data, ['id' => $id])
            ->execute();
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()
            ->delete('collection_policy', ['id' => $id])
            ->execute();
    }

    /**
     * Find analysis thresholds JSON for a policy.
     *
     * @return string|null The raw JSON string, or null if not found/not set
     */
    public function findAnalysisThresholds(int $id): ?string
    {
        $result = $this->db->createCommand(
            'SELECT analysis_thresholds FROM {{%collection_policy}} WHERE id = :id'
        )
            ->bindValue(':id', $id)
            ->queryScalar();

        if ($result === false || $result === null) {
            return null;
        }

        return (string) $result;
    }
}
