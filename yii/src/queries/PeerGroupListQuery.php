<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\peergroup\PeerGroupListResponse;
use app\dto\peergroup\PeerGroupResponse;
use DateTimeImmutable;
use yii\db\Connection;

/**
 * Query class for listing and retrieving peer groups in the admin UI.
 */
final class PeerGroupListQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * List all peer groups with optional filtering.
     *
     * @param string|null $sector Filter by sector
     * @param bool|null $isActive Filter by active status (null = all)
     * @param string|null $search Search in slug and name
     * @param string $orderBy Column to order by
     * @param string $orderDirection ASC or DESC
     */
    public function list(
        ?string $sector = null,
        ?bool $isActive = null,
        ?string $search = null,
        string $orderBy = 'name',
        string $orderDirection = 'ASC',
    ): PeerGroupListResponse {
        $allowedOrderColumns = ['name', 'slug', 'sector', 'is_active', 'created_at', 'updated_at'];
        if (!in_array($orderBy, $allowedOrderColumns, true)) {
            $orderBy = 'name';
        }
        $orderDirection = strtoupper($orderDirection) === 'DESC' ? 'DESC' : 'ASC';

        $sql = 'SELECT
                    g.id, g.slug, g.name, g.sector, g.description,
                    g.policy_id, p.name AS policy_name,
                    g.is_active, g.created_at, g.updated_at, g.created_by, g.updated_by,
                    COALESCE(member_stats.member_count, 0) AS member_count,
                    COALESCE(focal_stats.focal_count, 0) AS focal_count,
                    COALESCE(focal_stats.focal_tickers, \'\') AS focal_tickers,
                    last_run.status AS last_run_status,
                    last_run.started_at AS last_run_at
                FROM industry_peer_group g
                LEFT JOIN collection_policy p ON g.policy_id = p.id
                LEFT JOIN (
                    SELECT peer_group_id, COUNT(*) AS member_count
                    FROM industry_peer_group_member
                    GROUP BY peer_group_id
                ) member_stats ON g.id = member_stats.peer_group_id
                LEFT JOIN (
                    SELECT
                        m.peer_group_id,
                        COUNT(*) AS focal_count,
                        GROUP_CONCAT(c.ticker ORDER BY m.display_order, c.ticker SEPARATOR \', \') AS focal_tickers
                    FROM industry_peer_group_member m
                    JOIN company c ON m.company_id = c.id
                    WHERE m.is_focal = 1
                    GROUP BY m.peer_group_id
                ) focal_stats ON g.id = focal_stats.peer_group_id
                LEFT JOIN (
                    SELECT r1.industry_id, r1.status, r1.started_at
                    FROM collection_run r1
                    INNER JOIN (
                        SELECT industry_id, MAX(started_at) AS max_started_at
                        FROM collection_run
                        GROUP BY industry_id
                    ) r2 ON r1.industry_id = r2.industry_id AND r1.started_at = r2.max_started_at
                ) last_run ON g.slug = last_run.industry_id
                WHERE 1=1';

        $params = [];

        if ($sector !== null && $sector !== '') {
            $sql .= ' AND g.sector = :sector';
            $params[':sector'] = $sector;
        }

        if ($isActive === true) {
            $sql .= ' AND g.is_active = 1';
        } elseif ($isActive === false) {
            $sql .= ' AND g.is_active = 0';
        }

        if ($search !== null && $search !== '') {
            $sql .= ' AND (g.slug LIKE :search OR g.name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY g.{$orderBy} {$orderDirection}";

        $rows = $this->db->createCommand($sql)
            ->bindValues($params)
            ->queryAll();

        $groups = array_map(
            fn (array $row): PeerGroupResponse => $this->toResponse($row),
            $rows
        );

        return new PeerGroupListResponse(
            groups: $groups,
            counts: $this->getCounts(),
        );
    }

    /**
     * Find a single peer group by slug with full stats.
     */
    public function findBySlug(string $slug): ?PeerGroupResponse
    {
        $sql = 'SELECT
                    g.id, g.slug, g.name, g.sector, g.description,
                    g.policy_id, p.name AS policy_name,
                    g.is_active, g.created_at, g.updated_at, g.created_by, g.updated_by,
                    COALESCE(member_stats.member_count, 0) AS member_count,
                    COALESCE(focal_stats.focal_count, 0) AS focal_count,
                    COALESCE(focal_stats.focal_tickers, \'\') AS focal_tickers,
                    last_run.status AS last_run_status,
                    last_run.started_at AS last_run_at
                FROM industry_peer_group g
                LEFT JOIN collection_policy p ON g.policy_id = p.id
                LEFT JOIN (
                    SELECT peer_group_id, COUNT(*) AS member_count
                    FROM industry_peer_group_member
                    GROUP BY peer_group_id
                ) member_stats ON g.id = member_stats.peer_group_id
                LEFT JOIN (
                    SELECT
                        m.peer_group_id,
                        COUNT(*) AS focal_count,
                        GROUP_CONCAT(c.ticker ORDER BY m.display_order, c.ticker SEPARATOR \', \') AS focal_tickers
                    FROM industry_peer_group_member m
                    JOIN company c ON m.company_id = c.id
                    WHERE m.is_focal = 1
                    GROUP BY m.peer_group_id
                ) focal_stats ON g.id = focal_stats.peer_group_id
                LEFT JOIN (
                    SELECT r1.industry_id, r1.status, r1.started_at
                    FROM collection_run r1
                    INNER JOIN (
                        SELECT industry_id, MAX(started_at) AS max_started_at
                        FROM collection_run
                        GROUP BY industry_id
                    ) r2 ON r1.industry_id = r2.industry_id AND r1.started_at = r2.max_started_at
                ) last_run ON g.slug = last_run.industry_id
                WHERE g.slug = :slug';

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
            'SELECT COUNT(*) FROM industry_peer_group'
        )->queryScalar();

        $active = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM industry_peer_group WHERE is_active = 1'
        )->queryScalar();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }

    /**
     * Get all unique sectors for the sector dropdown.
     *
     * @return string[]
     */
    public function getSectors(): array
    {
        $rows = $this->db->createCommand(
            'SELECT DISTINCT sector FROM industry_peer_group ORDER BY sector'
        )->queryColumn();

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toResponse(array $row): PeerGroupResponse
    {
        $focalTickers = [];
        $focalTickersStr = $row['focal_tickers'] ?? '';
        if ($focalTickersStr !== '') {
            $focalTickers = array_map('trim', explode(',', $focalTickersStr));
        }

        return new PeerGroupResponse(
            id: (int) $row['id'],
            slug: $row['slug'],
            name: $row['name'],
            sector: $row['sector'],
            description: $row['description'] ?? null,
            policyId: $row['policy_id'] !== null ? (int) $row['policy_id'] : null,
            policyName: $row['policy_name'] ?? null,
            isActive: (bool) $row['is_active'],
            memberCount: (int) $row['member_count'],
            focalCount: (int) ($row['focal_count'] ?? 0),
            focalTickers: $focalTickers,
            lastRunStatus: $row['last_run_status'] ?? null,
            lastRunAt: $row['last_run_at'] !== null ? new DateTimeImmutable($row['last_run_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] ?? null,
            updatedBy: $row['updated_by'] ?? null,
        );
    }
}
