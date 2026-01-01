<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for peer group membership (company-to-group links).
 */
class PeerGroupMemberQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * @return array{company_id: int, is_focal: bool, display_order: int, ticker: string, name: string}[]
     */
    public function findByGroup(int $groupId): array
    {
        return $this->db->createCommand(
            'SELECT m.company_id, m.is_focal, m.display_order, c.ticker, c.name
             FROM industry_peer_group_member m
             JOIN company c ON m.company_id = c.id
             WHERE m.peer_group_id = :groupId
             ORDER BY m.display_order, c.ticker'
        )
            ->bindValue(':groupId', $groupId)
            ->queryAll();
    }

    /**
     * @return array{company_id: int, ticker: string, name: string}|null
     */
    public function findFocalByGroup(int $groupId): ?array
    {
        $row = $this->db->createCommand(
            'SELECT m.company_id, c.ticker, c.name
             FROM industry_peer_group_member m
             JOIN company c ON m.company_id = c.id
             WHERE m.peer_group_id = :groupId AND m.is_focal = TRUE'
        )
            ->bindValue(':groupId', $groupId)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function isMember(int $groupId, int $companyId): bool
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM industry_peer_group_member
             WHERE peer_group_id = :groupId AND company_id = :companyId'
        )
            ->bindValues([':groupId' => $groupId, ':companyId' => $companyId])
            ->queryScalar();

        return (int) $count > 0;
    }

    public function addMember(int $groupId, int $companyId, bool $isFocal = false, int $displayOrder = 0, ?string $addedBy = null): void
    {
        $this->db->createCommand()
            ->insert('industry_peer_group_member', [
                'peer_group_id' => $groupId,
                'company_id' => $companyId,
                'is_focal' => $isFocal ? 1 : 0,
                'display_order' => $displayOrder,
                'added_by' => $addedBy,
            ])
            ->execute();
    }

    public function removeMember(int $groupId, int $companyId): void
    {
        $this->db->createCommand()
            ->delete('industry_peer_group_member', [
                'peer_group_id' => $groupId,
                'company_id' => $companyId,
            ])
            ->execute();
    }

    public function setFocal(int $groupId, int $companyId): void
    {
        // Clear existing focal
        $this->clearFocal($groupId);

        // Set new focal
        $this->db->createCommand()
            ->update(
                'industry_peer_group_member',
                ['is_focal' => 1],
                ['peer_group_id' => $groupId, 'company_id' => $companyId]
            )
            ->execute();
    }

    public function clearFocal(int $groupId): void
    {
        $this->db->createCommand()
            ->update(
                'industry_peer_group_member',
                ['is_focal' => 0],
                ['peer_group_id' => $groupId, 'is_focal' => 1]
            )
            ->execute();
    }

    public function updateDisplayOrder(int $groupId, int $companyId, int $displayOrder): void
    {
        $this->db->createCommand()
            ->update(
                'industry_peer_group_member',
                ['display_order' => $displayOrder],
                ['peer_group_id' => $groupId, 'company_id' => $companyId]
            )
            ->execute();
    }

    public function countByGroup(int $groupId): int
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM industry_peer_group_member WHERE peer_group_id = :groupId'
        )
            ->bindValue(':groupId', $groupId)
            ->queryScalar();

        return (int) $count;
    }

    public function removeAllFromGroup(int $groupId): int
    {
        return $this->db->createCommand()
            ->delete('industry_peer_group_member', ['peer_group_id' => $groupId])
            ->execute();
    }
}
