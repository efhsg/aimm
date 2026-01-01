<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for industry peer groups.
 */
class PeerGroupQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM industry_peer_group WHERE id = :id'
        )
            ->bindValue(':id', $id)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findBySlug(string $slug): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM industry_peer_group WHERE slug = :slug'
        )
            ->bindValue(':slug', $slug)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findAllActive(): array
    {
        return $this->db->createCommand(
            'SELECT * FROM industry_peer_group WHERE is_active = TRUE ORDER BY sector, name'
        )
            ->queryAll();
    }

    public function findBySector(string $sector, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM industry_peer_group WHERE sector = :sector';
        if ($activeOnly) {
            $sql .= ' AND is_active = TRUE';
        }
        $sql .= ' ORDER BY name';

        return $this->db->createCommand($sql)
            ->bindValue(':sector', $sector)
            ->queryAll();
    }

    public function findByCompanyId(int $companyId): array
    {
        return $this->db->createCommand(
            'SELECT g.* FROM industry_peer_group g
             JOIN industry_peer_group_member m ON g.id = m.peer_group_id
             WHERE m.company_id = :companyId AND g.is_active = TRUE
             ORDER BY g.sector, g.name'
        )
            ->bindValue(':companyId', $companyId)
            ->queryAll();
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('industry_peer_group', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function update(int $id, array $data): void
    {
        $this->db->createCommand()
            ->update('industry_peer_group', $data, ['id' => $id])
            ->execute();
    }

    public function deactivate(int $id): void
    {
        $this->db->createCommand()
            ->update('industry_peer_group', ['is_active' => false], ['id' => $id])
            ->execute();
    }

    public function activate(int $id): void
    {
        $this->db->createCommand()
            ->update('industry_peer_group', ['is_active' => true], ['id' => $id])
            ->execute();
    }

    public function delete(int $id): void
    {
        $this->db->createCommand()
            ->delete('industry_peer_group', ['id' => $id])
            ->execute();
    }

    public function assignPolicy(int $groupId, ?int $policyId): void
    {
        $this->db->createCommand()
            ->update('industry_peer_group', ['policy_id' => $policyId], ['id' => $groupId])
            ->execute();
    }

    /**
     * @return array{slug: string, name: string, sector: string, member_count: int, has_focal: bool}[]
     */
    public function findAllWithStats(): array
    {
        return $this->db->createCommand(
            'SELECT
                g.id, g.slug, g.name, g.sector, g.is_active, g.policy_id,
                COUNT(m.company_id) AS member_count,
                COALESCE(SUM(m.is_focal), 0) > 0 AS has_focal
             FROM industry_peer_group g
             LEFT JOIN industry_peer_group_member m ON g.id = m.peer_group_id
             GROUP BY g.id
             ORDER BY g.sector, g.name'
        )
            ->queryAll();
    }
}
