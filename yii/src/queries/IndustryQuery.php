<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for industries.
 */
final class IndustryQuery
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
            'SELECT i.*, s.name as sector_name, s.slug as sector_slug
             FROM {{%industry}} i
             JOIN {{%sector}} s ON s.id = i.sector_id
             WHERE i.id = :id'
        )->bindValue(':id', $id)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->createCommand(
            'SELECT i.*, s.name as sector_name, s.slug as sector_slug
             FROM {{%industry}} i
             JOIN {{%sector}} s ON s.id = i.sector_id
             WHERE i.slug = :slug'
        )->bindValue(':slug', $slug)->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllActive(): array
    {
        return $this->db->createCommand(
            'SELECT i.*, s.name as sector_name, s.slug as sector_slug
             FROM {{%industry}} i
             JOIN {{%sector}} s ON s.id = i.sector_id
             WHERE i.is_active = 1
             ORDER BY s.name, i.name'
        )->queryAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findBySectorId(int $sectorId, bool $activeOnly = true): array
    {
        $sql = 'SELECT i.*, s.name as sector_name, s.slug as sector_slug
                FROM {{%industry}} i
                JOIN {{%sector}} s ON s.id = i.sector_id
                WHERE i.sector_id = :sectorId';

        if ($activeOnly) {
            $sql .= ' AND i.is_active = 1';
        }
        $sql .= ' ORDER BY i.name';

        return $this->db->createCommand($sql)
            ->bindValue(':sectorId', $sectorId)
            ->queryAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('{{%industry}}', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->createCommand()
            ->update('{{%industry}}', $data, ['id' => $id])
            ->execute();
    }

    public function deactivate(int $id): void
    {
        $this->db->createCommand()
            ->update('{{%industry}}', ['is_active' => false], ['id' => $id])
            ->execute();
    }

    public function activate(int $id): void
    {
        $this->db->createCommand()
            ->update('{{%industry}}', ['is_active' => true], ['id' => $id])
            ->execute();
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()
            ->delete('{{%industry}}', ['id' => $id])
            ->execute();
    }

    public function assignPolicy(int $industryId, ?int $policyId): void
    {
        $this->db->createCommand()
            ->update('{{%industry}}', ['policy_id' => $policyId], ['id' => $industryId])
            ->execute();
    }

    /**
     * Find all industries with company counts.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllWithStats(): array
    {
        return $this->db->createCommand(
            'SELECT
                i.id, i.slug, i.name, i.sector_id, i.is_active, i.policy_id,
                s.name as sector_name, s.slug as sector_slug,
                COUNT(c.id) AS company_count
             FROM {{%industry}} i
             JOIN {{%sector}} s ON s.id = i.sector_id
             LEFT JOIN {{%company}} c ON c.industry_id = i.id
             GROUP BY i.id
             ORDER BY s.name, i.name'
        )->queryAll();
    }
}
