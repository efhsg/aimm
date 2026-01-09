<?php

declare(strict_types=1);

namespace app\dto\industry;

use app\dto\GateResult;
use app\enums\CollectionStatus;

/**
 * Result of collecting data for an industry.
 */
final readonly class CollectIndustryResult
{
    /**
     * @param string[] $errors
     */
    private function __construct(
        public bool $success,
        public ?int $runId,
        public ?string $datapackId,
        public ?CollectionStatus $status,
        public ?GateResult $gateResult,
        public array $errors,
    ) {
    }

    public static function success(
        int $runId,
        string $datapackId,
        CollectionStatus $status,
        GateResult $gateResult,
    ): self {
        return new self(
            success: true,
            runId: $runId,
            datapackId: $datapackId,
            status: $status,
            gateResult: $gateResult,
            errors: [],
        );
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(
            success: false,
            runId: null,
            datapackId: null,
            status: null,
            gateResult: null,
            errors: $errors,
        );
    }
}
