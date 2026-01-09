<?php

declare(strict_types=1);

namespace app\queries;

use app\enums\PdfJobStatus;
use app\models\PdfJob;
use DateTimeImmutable;
use yii\db\Connection;

/**
 * Repository for PDF job persistence and state management.
 */
final class PdfJobRepository implements PdfJobRepositoryInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * Find an existing job or create a new one.
     *
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for idempotency.
     * Returns the job regardless of whether it was created or already existed.
     */
    public function findOrCreate(
        string $reportId,
        string $paramsHash,
        string $traceId,
        ?string $requesterId = null,
    ): PdfJob {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // Try to insert, ignore on duplicate (idempotent)
        $this->db->createCommand(
            'INSERT INTO {{%pdf_job}} (report_id, params_hash, trace_id, requester_id, status, attempts, created_at, updated_at)
             VALUES (:report_id, :params_hash, :trace_id, :requester_id, :status, 0, :now, :now)
             ON DUPLICATE KEY UPDATE id = id',
        )
            ->bindValue(':report_id', $reportId)
            ->bindValue(':params_hash', $paramsHash)
            ->bindValue(':trace_id', $traceId)
            ->bindValue(':requester_id', $requesterId)
            ->bindValue(':status', PdfJobStatus::Pending->value)
            ->bindValue(':now', $now)
            ->execute();

        // Fetch the job (either new or existing)
        return $this->findByReportAndParams($reportId, $paramsHash);
    }

    /**
     * Find a job by report ID and params hash.
     */
    public function findByReportAndParams(string $reportId, string $paramsHash): ?PdfJob
    {
        return PdfJob::find()
            ->where(['report_id' => $reportId, 'params_hash' => $paramsHash])
            ->one();
    }

    /**
     * Find a job by ID.
     */
    public function findById(int $id): ?PdfJob
    {
        return PdfJob::findOne($id);
    }

    /**
     * Find and lock a job for update (must be called within a transaction).
     */
    public function findAndLock(int $jobId): ?PdfJob
    {
        $row = $this->db->createCommand(
            'SELECT * FROM {{%pdf_job}} WHERE id = :id FOR UPDATE',
        )->bindValue(':id', $jobId)->queryOne();

        if ($row === false) {
            return null;
        }

        $job = new PdfJob();
        $job->setAttributes($row, false);
        $job->setOldAttributes($row);

        return $job;
    }

    /**
     * Transition a job to a new status.
     *
     * Returns true if the transition was successful (job was in expected state).
     */
    public function transitionTo(int $jobId, PdfJobStatus $from, PdfJobStatus $to): bool
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $updates = [
            'status' => $to->value,
            'updated_at' => $now,
        ];

        if ($to->isTerminal()) {
            $updates['finished_at'] = $now;
        }

        $result = $this->db->createCommand()->update(
            '{{%pdf_job}}',
            $updates,
            ['id' => $jobId, 'status' => $from->value],
        )->execute();

        return $result > 0;
    }

    /**
     * Mark a job as complete with output URI.
     */
    public function complete(int $jobId, string $outputUri): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->createCommand()->update(
            '{{%pdf_job}}',
            [
                'status' => PdfJobStatus::Complete->value,
                'output_uri' => $outputUri,
                'updated_at' => $now,
                'finished_at' => $now,
            ],
            ['id' => $jobId],
        )->execute();
    }

    /**
     * Mark a job as failed with error details.
     */
    public function fail(int $jobId, string $errorCode, string $errorMessage): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->db->createCommand()->update(
            '{{%pdf_job}}',
            [
                'status' => PdfJobStatus::Failed->value,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'updated_at' => $now,
                'finished_at' => $now,
            ],
            ['id' => $jobId],
        )->execute();
    }

    /**
     * Increment the attempt counter.
     */
    public function incrementAttempts(int $jobId): void
    {
        $this->db->createCommand(
            'UPDATE {{%pdf_job}} SET attempts = attempts + 1, updated_at = :now WHERE id = :id',
        )
            ->bindValue(':id', $jobId)
            ->bindValue(':now', (new DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->execute();
    }

    /**
     * Find the latest completed job for a report ID.
     */
    public function findLatestCompleted(string $reportId): ?PdfJob
    {
        return PdfJob::find()
            ->where(['report_id' => $reportId, 'status' => PdfJobStatus::Complete->value])
            ->andWhere(['not', ['output_uri' => null]])
            ->orderBy(['finished_at' => SORT_DESC])
            ->one();
    }
}
