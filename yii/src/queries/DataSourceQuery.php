<?php

declare(strict_types=1);

namespace app\queries;

use yii\db\Connection;

/**
 * Queries for data sources (external data providers).
 */
final class DataSourceQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Find a data source by ID.
     */
    public function findById(string $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM data_source WHERE id = :id'
        )
            ->bindValue(':id', $id)
            ->queryOne();

        return $row === false ? null : $row;
    }

    /**
     * Find all data sources.
     *
     * @return array<string, mixed>[]
     */
    public function findAll(): array
    {
        return $this->db->createCommand(
            'SELECT * FROM data_source ORDER BY name'
        )
            ->queryAll();
    }

    /**
     * List data sources with optional filtering.
     *
     * @param string|null $status Filter by status ('active', 'inactive', or null for all)
     * @param string|null $type Filter by source_type
     * @param string|null $search Search in id and name
     * @return array<string, mixed>[]
     */
    public function list(
        ?string $status = null,
        ?string $type = null,
        ?string $search = null,
    ): array {
        $sql = 'SELECT * FROM data_source WHERE 1=1';
        $params = [];

        if ($status === 'active') {
            $sql .= ' AND is_active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND is_active = 0';
        }

        if ($type !== null && $type !== '') {
            $sql .= ' AND source_type = :type';
            $params[':type'] = $type;
        }

        if ($search !== null && $search !== '') {
            $sql .= ' AND (id LIKE :search OR name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY name';

        $command = $this->db->createCommand($sql);
        foreach ($params as $name => $value) {
            $command->bindValue($name, $value);
        }

        return $command->queryAll();
    }

    /**
     * Get counts by status.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function getCounts(): array
    {
        $row = $this->db->createCommand(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
            FROM data_source'
        )
            ->queryOne();

        $total = (int) ($row['total'] ?? 0);
        $active = (int) ($row['active'] ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }

    /**
     * Find collection policies that use a data source.
     *
     * @return array<string, mixed>[]
     */
    public function findPoliciesUsingSource(string $sourceId): array
    {
        $policies = $this->db->createCommand(
            'SELECT id, slug, name, source_priorities FROM collection_policy WHERE source_priorities IS NOT NULL'
        )->queryAll();

        $usingPolicies = [];
        foreach ($policies as $policy) {
            $priorities = json_decode($policy['source_priorities'], true);
            if (!is_array($priorities)) {
                continue;
            }

            $categories = [];
            foreach ($priorities as $category => $sources) {
                if (is_array($sources) && in_array($sourceId, $sources, true)) {
                    $categories[] = $category;
                }
            }

            if (!empty($categories)) {
                $usingPolicies[] = [
                    'id' => $policy['id'],
                    'slug' => $policy['slug'],
                    'name' => $policy['name'],
                    'categories' => $categories,
                ];
            }
        }

        return $usingPolicies;
    }
}
