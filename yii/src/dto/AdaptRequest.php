<?php

declare(strict_types=1);

namespace app\dto;

/**
 * Request for adapting fetched content to structured extractions.
 */
final readonly class AdaptRequest
{
    /**
     * @param list<string> $datapointKeys
     */
    public function __construct(
        public FetchResult $fetchResult,
        public array $datapointKeys,
        public ?string $ticker = null,
    ) {
    }
}
