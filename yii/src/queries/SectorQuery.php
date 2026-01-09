<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Query operations for sectors.
 */
final class SectorQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%sector}} WHERE id = :id'
        )->bindValue(':id', $id)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%sector}} WHERE slug = :slug'
        )->bindValue(':slug', $slug)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%sector}} WHERE name = :name'
        )->bindValue(':name', $name)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%sector}} ORDER BY name ASC'
        )->queryAll();
    }

    /**
     * Find all sectors with industry counts.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllWithCounts(): array
    {
        return $this->db->createCommand(
            'SELECT s.*, COUNT(i.id) as industry_count
             FROM {{%sector}} s
             LEFT JOIN {{%industry}} i ON i.sector_id = s.id
             GROUP BY s.id
             ORDER BY s.name ASC'
        )->queryAll();
    }

    public function insert(string $slug, string $name): int
    {
        $this->db->createCommand()->insert('{{%sector}}', [
            'slug' => $slug,
            'name' => $name,
        ])->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function update(int $id, string $name): void
    {
        $this->db->createCommand()->update(
            '{{%sector}}',
            ['name' => $name],
            ['id' => $id]
        )->execute();
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()->delete(
            '{{%sector}}',
            ['id' => $id]
        )->execute();
    }
}
