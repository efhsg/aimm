<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for company membership within an industry.
 *
 * Companies have a direct `industry_id` FK to the industry table.
 */
final class IndustryMemberQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Find companies belonging to an industry.
     *
     * @return array{company_id: int, ticker: string, name: string}[]
     */
    public function findByIndustry(int $industryId): array
    {
        return $this->db->createCommand(
            'SELECT c.id AS company_id, c.ticker, c.name
             FROM company c
             WHERE c.industry_id = :industryId
             ORDER BY c.ticker'
        )
            ->bindValue(':industryId', $industryId)
            ->queryAll();
    }

    /**
     * Check if a company belongs to an industry.
     */
    public function isMember(int $industryId, int $companyId): bool
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM company
             WHERE id = :companyId AND industry_id = :industryId'
        )
            ->bindValues([':industryId' => $industryId, ':companyId' => $companyId])
            ->queryScalar();

        return (int) $count > 0;
    }

    /**
     * Assign a company to an industry.
     */
    public function addMember(int $industryId, int $companyId): void
    {
        $this->db->createCommand()
            ->update('company', ['industry_id' => $industryId], ['id' => $companyId])
            ->execute();
    }

    /**
     * Remove a company from an industry (set industry_id to NULL).
     */
    public function removeMember(int $industryId, int $companyId): void
    {
        $this->db->createCommand()
            ->update(
                'company',
                ['industry_id' => null],
                ['id' => $companyId, 'industry_id' => $industryId]
            )
            ->execute();
    }

    /**
     * Count companies in an industry.
     */
    public function countByIndustry(int $industryId): int
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM company WHERE industry_id = :industryId'
        )
            ->bindValue(':industryId', $industryId)
            ->queryScalar();

        return (int) $count;
    }

    /**
     * Remove all companies from an industry (set their industry_id to NULL).
     */
    public function removeAllFromIndustry(int $industryId): int
    {
        return $this->db->createCommand()
            ->update('company', ['industry_id' => null], ['industry_id' => $industryId])
            ->execute();
    }
}
