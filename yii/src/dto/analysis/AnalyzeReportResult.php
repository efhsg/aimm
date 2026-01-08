<?php

declare(strict_types=1);

namespace app\dto\analysis;

use app\dto\GateResult;
use app\dto\report\ReportDTO;

/**
 * Result of analysis pipeline.
 */
final readonly class AnalyzeReportResult
{
    private function __construct(
        public bool $success,
        public ?ReportDTO $report,
        public ?GateResult $gateResult,
        public ?string $errorMessage,
    ) {
    }

    public static function success(ReportDTO $report): self
    {
        return new self(
            success: true,
            report: $report,
            gateResult: null,
            errorMessage: null,
        );
    }

    public static function gateFailed(GateResult $gateResult): self
    {
        return new self(
            success: false,
            report: null,
            gateResult: $gateResult,
            errorMessage: 'Gate validation failed',
        );
    }

    public static function error(string $message): self
    {
        return new self(
            success: false,
            report: null,
            gateResult: null,
            errorMessage: $message,
        );
    }
}
