<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\industry\IndustryListResponse;
use app\dto\industry\IndustryResponse;
use DateTimeImmutable;
use yii\db\Connection;

/**
 * Query class for listing and retrieving industries in the admin UI.
 */
final class IndustryListQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * List all industries with optional filtering.
     *
     * @param int|null $sectorId Filter by sector ID
     * @param bool|null $isActive Filter by active status (null = all)
     * @param string|null $search Search in slug and name
     * @param string $orderBy Column to order by
     * @param string $orderDirection ASC or DESC
     */
    public function list(
        ?int $sectorId = null,
        ?bool $isActive = null,
        ?string $search = null,
        string $orderBy = 'name',
        string $orderDirection = 'ASC',
    ): IndustryListResponse {
        $allowedOrderColumns = ['name', 'slug', 'sector_name', 'is_active', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns, true)) {
            $orderBy = 'name';
        }
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $orderColumn = $orderBy === 'sector_name' ? 's.name' : "i.{$orderBy}";

        $sql = 'SELECT
                    i.id, i.slug, i.name, i.sector_id, i.description,
                    i.policy_id, p.name AS policy_name,
                    i.is_active, i.created_at, i.updated_at, i.created_by, i.updated_by,
                    s.slug AS sector_slug, s.name AS sector_name,
                    COALESCE(company_stats.company_count, 0) AS company_count,
                    last_run.status AS last_run_status,
                    last_run.started_at AS last_run_at
                FROM {{%industry}} i
                JOIN {{%sector}} s ON s.id = i.sector_id
                LEFT JOIN {{%collection_policy}} p ON i.policy_id = p.id
                LEFT JOIN (
                    SELECT industry_id, COUNT(*) AS company_count
                    FROM {{%company}}
                    WHERE industry_id IS NOT NULL
                    GROUP BY industry_id
                ) company_stats ON i.id = company_stats.industry_id
                LEFT JOIN (
                    SELECT r1.industry_id, r1.status, r1.started_at
                    FROM {{%collection_run}} r1
                    INNER JOIN (
                        SELECT industry_id, MAX(started_at) AS max_started_at
                        FROM {{%collection_run}}
                        GROUP BY industry_id
                    ) r2 ON r1.industry_id = r2.industry_id AND r1.started_at = r2.max_started_at
                ) last_run ON i.id = last_run.industry_id
                WHERE 1=1';

        $params = [];

        if ($sectorId !== null) {
            $sql .= ' AND i.sector_id = :sectorId';
            $params[':sectorId'] = $sectorId;
        }

        if ($isActive === true) {
            $sql .= ' AND i.is_active = 1';
        } elseif ($isActive === false) {
            $sql .= ' AND i.is_active = 0';
        }

        if ($search !== null && $search !== '') {
            $sql .= ' AND (i.slug LIKE :search OR i.name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY {$orderColumn} {$orderDirection}";

        $rows = $this->db->createCommand($sql)
            ->bindValues($params)
            ->queryAll();

        $industries = array_map(
            fn (array $row): IndustryResponse => $this->toResponse($row),
            $rows
        );

        return new IndustryListResponse(
            industries: $industries,
            counts: $this->getCounts(),
        );
    }

    /**
     * Find a single industry by slug with full stats.
     */
    public function findBySlug(string $slug): ?IndustryResponse
    {
        $sql = 'SELECT
                    i.id, i.slug, i.name, i.sector_id, i.description,
                    i.policy_id, p.name AS policy_name,
                    i.is_active, i.created_at, i.updated_at, i.created_by, i.updated_by,
                    s.slug AS sector_slug, s.name AS sector_name,
                    COALESCE(company_stats.company_count, 0) AS company_count,
                    last_run.status AS last_run_status,
                    last_run.started_at AS last_run_at
                FROM {{%industry}} i
                JOIN {{%sector}} s ON s.id = i.sector_id
                LEFT JOIN {{%collection_policy}} p ON i.policy_id = p.id
                LEFT JOIN (
                    SELECT industry_id, COUNT(*) AS company_count
                    FROM {{%company}}
                    WHERE industry_id IS NOT NULL
                    GROUP BY industry_id
                ) company_stats ON i.id = company_stats.industry_id
                LEFT JOIN (
                    SELECT r1.industry_id, r1.status, r1.started_at
                    FROM {{%collection_run}} r1
                    INNER JOIN (
                        SELECT industry_id, MAX(started_at) AS max_started_at
                        FROM {{%collection_run}}
                        GROUP BY industry_id
                    ) r2 ON r1.industry_id = r2.industry_id AND r1.started_at = r2.max_started_at
                ) last_run ON i.id = last_run.industry_id
                WHERE i.slug = :slug';

        $row = $this->db->createCommand($sql)
            ->bindValue(':slug', $slug)
            ->queryOne();

        if ($row === false) {
            return null;
        }

        return $this->toResponse($row);
    }

    /**
     * Get counts by active status.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function getCounts(): array
    {
        $total = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM {{%industry}}'
        )->queryScalar();

        $active = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM {{%industry}} WHERE is_active = 1'
        )->queryScalar();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toResponse(array $row): IndustryResponse
    {
        return new IndustryResponse(
            id: (int) $row['id'],
            slug: $row['slug'],
            name: $row['name'],
            sectorId: (int) $row['sector_id'],
            sectorSlug: $row['sector_slug'],
            sectorName: $row['sector_name'],
            description: $row['description'] ?? null,
            policyId: $row['policy_id'] !== null ? (int) $row['policy_id'] : null,
            policyName: $row['policy_name'] ?? null,
            isActive: (bool) $row['is_active'],
            companyCount: (int) $row['company_count'],
            lastRunStatus: $row['last_run_status'] ?? null,
            lastRunAt: $row['last_run_at'] !== null ? new DateTimeImmutable($row['last_run_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] ?? null,
            updatedBy: $row['updated_by'] ?? null,
        );
    }
}
