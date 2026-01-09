<?php

declare(strict_types=1);

namespace app\enums;

/**
 * Status of a PDF generation job.
 */
enum PdfJobStatus: string
{
    case Pending = 'pending';
    case Rendering = 'rendering';
    case Complete = 'complete';
    case Failed = 'failed';

    /**
     * Check if the job can transition to the given status.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Rendering || $target === self::Failed,
            self::Rendering => $target === self::Complete || $target === self::Failed,
            self::Complete, self::Failed => false,
        };
    }

    /**
     * Check if the job is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::Complete || $this === self::Failed;
    }
}
