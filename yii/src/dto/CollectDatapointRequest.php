<?php

declare(strict_types=1);

namespace app\dto;

use app\clients\FmpResponseCache;
use app\enums\Severity;
use DateTimeImmutable;

/**
 * Input DTO for collecting a single datapoint.
 */
final readonly class CollectDatapointRequest
{
    /**
     * @param SourceCandidate[] $sourceCandidates Priority-ordered list of sources to try
     */
    public function __construct(
        public string $datapointKey,
        public array $sourceCandidates,
        public string $adapterId,
        public Severity $severity,
        public ?string $ticker = null,
        public ?DateTimeImmutable $asOfMin = null,
        public ?string $unit = null,
        public ?FmpResponseCache $fmpResponseCache = null,
    ) {
    }
}
