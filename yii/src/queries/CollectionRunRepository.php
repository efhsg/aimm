<?php

declare(strict_types=1);

namespace app\queries;

use app\dto\GateResult;
use DateTimeImmutable;
use yii\db\Connection;

/**
 * Repository for collection run persistence.
 */
final class CollectionRunRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function create(string $industryId, string $datapackId): int
    {
        $this->db->createCommand()->insert('{{%collection_run}}', [
            'industry_id' => $industryId,
            'datapack_id' => $datapackId,
            'status' => 'running',
            'started_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ])->execute();

        return (int) $this->db->getLastInsertID();
    }

    public function updateProgress(
        int $runId,
        int $companiesTotal,
        int $companiesSuccess,
        int $companiesFailed,
    ): void {
        $this->db->createCommand()->update(
            '{{%collection_run}}',
            [
                'companies_total' => $companiesTotal,
                'companies_success' => $companiesSuccess,
                'companies_failed' => $companiesFailed,
            ],
            ['id' => $runId],
        )->execute();
    }

    public function complete(
        int $runId,
        string $status,
        bool $gatePassed,
        int $errorCount,
        int $warningCount,
        string $filePath,
        int $fileSizeBytes,
        int $durationSeconds,
    ): void {
        $this->db->createCommand()->update(
            '{{%collection_run}}',
            [
                'status' => $status,
                'completed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'gate_passed' => $gatePassed ? 1 : 0,
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'file_path' => $filePath,
                'file_size_bytes' => $fileSizeBytes,
                'duration_seconds' => $durationSeconds,
            ],
            ['id' => $runId],
        )->execute();
    }

    public function recordErrors(int $runId, GateResult $gateResult): void
    {
        foreach ($gateResult->errors as $error) {
            $this->db->createCommand()->insert('{{%collection_error}}', [
                'collection_run_id' => $runId,
                'severity' => 'error',
                'error_code' => $error->code,
                'error_message' => $error->message,
                'error_path' => $error->path,
                'ticker' => $this->extractTicker($error->path),
            ])->execute();
        }

        foreach ($gateResult->warnings as $warning) {
            $this->db->createCommand()->insert('{{%collection_error}}', [
                'collection_run_id' => $runId,
                'severity' => 'warning',
                'error_code' => $warning->code,
                'error_message' => $warning->message,
                'error_path' => $warning->path,
                'ticker' => $this->extractTicker($warning->path),
            ])->execute();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%collection_run}} WHERE id = :id',
        )->bindValue(':id', $id)->queryOne() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByDatapackId(string $datapackId): ?array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%collection_run}} WHERE datapack_id = :id',
        )->bindValue(':id', $datapackId)->queryOne() ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByIndustry(string $industryId, int $limit = 20): array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%collection_run}}
             WHERE industry_id = :industry_id
             ORDER BY started_at DESC
             LIMIT :limit',
        )
            ->bindValue(':industry_id', $industryId)
            ->bindValue(':limit', $limit)
            ->queryAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLatestSuccessful(string $industryId): ?array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%collection_run}}
             WHERE industry_id = :industry_id
               AND status = :status
               AND gate_passed = 1
             ORDER BY completed_at DESC
             LIMIT 1',
        )
            ->bindValue(':industry_id', $industryId)
            ->bindValue(':status', 'complete')
            ->queryOne() ?: null;
    }

    public function extractTicker(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (preg_match('/companies\.([A-Z0-9.]+)\./', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get errors and warnings for a collection run.
     *
     * @return list<array<string, mixed>>
     */
    public function getErrors(int $runId): array
    {
        return $this->db->createCommand(
            'SELECT * FROM {{%collection_error}}
             WHERE collection_run_id = :run_id
             ORDER BY severity ASC, id ASC',
        )
            ->bindValue(':run_id', $runId)
            ->queryAll();
    }

    /**
     * List collection runs for a peer group.
     *
     * @return list<array<string, mixed>>
     */
    public function listByPeerGroup(int $peerGroupId, int $limit = 20): array
    {
        return $this->db->createCommand(
            'SELECT cr.* FROM {{%collection_run}} cr
             JOIN {{%industry_peer_group}} pg ON cr.industry_id = pg.slug
             WHERE pg.id = :group_id
             ORDER BY cr.started_at DESC
             LIMIT :limit',
        )
            ->bindValue(':group_id', $peerGroupId)
            ->bindValue(':limit', $limit)
            ->queryAll();
    }

    /**
     * Check if a peer group has a running collection.
     */
    public function hasRunningCollection(int $peerGroupId): bool
    {
        $count = $this->db->createCommand(
            'SELECT COUNT(*) FROM {{%collection_run}} cr
             JOIN {{%industry_peer_group}} pg ON cr.industry_id = pg.slug
             WHERE pg.id = :group_id AND cr.status = :status',
        )
            ->bindValue(':group_id', $peerGroupId)
            ->bindValue(':status', 'running')
            ->queryScalar();

        return (int) $count > 0;
    }
}
