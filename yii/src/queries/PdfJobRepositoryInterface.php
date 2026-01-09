<?php

declare(strict_types=1);

namespace app\queries;

use app\enums\PdfJobStatus;
use app\models\PdfJob;

/**
 * Interface for PDF job repository operations.
 *
 * Extracted from PdfJobRepository to support testing.
 */
interface PdfJobRepositoryInterface
{
    /**
     * Find an existing job or create a new one.
     */
    public function findOrCreate(
        string $reportId,
        string $paramsHash,
        string $traceId,
        ?string $requesterId = null,
    ): PdfJob;

    /**
     * Find a job by ID.
     */
    public function findById(int $id): ?PdfJob;

    /**
     * Find and lock a job for update (must be called within a transaction).
     */
    public function findAndLock(int $jobId): ?PdfJob;

    /**
     * Transition a job to a new status.
     */
    public function transitionTo(int $jobId, PdfJobStatus $from, PdfJobStatus $to): bool;

    /**
     * Mark a job as complete with output URI.
     */
    public function complete(int $jobId, string $outputUri): void;

    /**
     * Mark a job as failed with error details.
     */
    public function fail(int $jobId, string $errorCode, string $errorMessage): void;

    /**
     * Increment the attempt counter.
     */
    public function incrementAttempts(int $jobId): void;

    /**
     * Find the latest completed job for a report ID.
     */
    public function findLatestCompleted(string $reportId): ?PdfJob;
}
