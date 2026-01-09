<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for company records with staleness tracking.
 */
class CompanyQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM company WHERE id = :id'
        )
            ->bindValue(':id', $id)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findByTicker(string $ticker): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM company WHERE ticker = :ticker'
        )
            ->bindValue(':ticker', $ticker)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findOrCreate(string $ticker): int
    {
        $existing = $this->findByTicker($ticker);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $this->db->createCommand()
            ->insert('company', [
                'ticker' => $ticker,
                'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ])
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function updateStaleness(int $id, string $field, DateTimeImmutable $at): void
    {
        // Allowed fields validation to prevent SQL injection
        $allowed = [
            'financials_collected_at',
            'quarters_collected_at',
            'valuation_collected_at',
            'profile_collected_at'
        ];

        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid staleness field: $field");
        }

        $this->db->createCommand()
            ->update('company', [$field => $at->format('Y-m-d H:i:s')], ['id' => $id])
            ->execute();
    }

    /**
     * Find all companies.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        return $this->db->createCommand(
            'SELECT * FROM company ORDER BY ticker'
        )->queryAll();
    }

    /**
     * Find companies by industry ID.
     *
     * @return list<array<string, mixed>>
     */
    public function findByIndustry(int $industryId): array
    {
        return $this->db->createCommand(
            'SELECT * FROM company WHERE industry_id = :industryId ORDER BY ticker'
        )
            ->bindValue(':industryId', $industryId)
            ->queryAll();
    }

    /**
     * Count companies by industry ID.
     */
    public function countByIndustry(int $industryId): int
    {
        return (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM company WHERE industry_id = :industryId'
        )
            ->bindValue(':industryId', $industryId)
            ->queryScalar();
    }
}
