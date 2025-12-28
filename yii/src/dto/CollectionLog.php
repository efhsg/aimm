<?php

declare(strict_types=1);

namespace app\dto;

use app\enums\CollectionStatus;
use DateTimeImmutable;

/**
 * Log of a collection run with timing and status information.
 */
final readonly class CollectionLog
{
    /**
     * @param array<string, CollectionStatus> $companyStatuses Indexed by ticker
     */
    public function __construct(
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $completedAt,
        public int $durationSeconds,
        public array $companyStatuses,
        public CollectionStatus $macroStatus,
        public int $totalAttempts,
    ) {
    }

    /**
     * Get count of companies by status.
     */
    public function countByStatus(CollectionStatus $status): int
    {
        return count(array_filter(
            $this->companyStatuses,
            static fn (CollectionStatus $s) => $s === $status
        ));
    }

    /**
     * Get overall success rate as a percentage.
     */
    public function getSuccessRate(): float
    {
        $total = count($this->companyStatuses);
        if ($total === 0) {
            return 0.0;
        }

        $complete = $this->countByStatus(CollectionStatus::Complete);
        return ($complete / $total) * 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'started_at' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completed_at' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration_seconds' => $this->durationSeconds,
            'company_statuses' => array_map(
                static fn (CollectionStatus $s) => $s->value,
                $this->companyStatuses
            ),
            'macro_status' => $this->macroStatus->value,
            'total_attempts' => $this->totalAttempts,
        ];
    }
}
